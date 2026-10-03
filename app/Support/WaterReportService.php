<?php

namespace App\Support;

use App\Models\Province;
use App\Models\User;
use App\Models\WaterReport;
use App\Models\WaterReportVote;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * รายงานระดับน้ำจากประชาชน ทีม และเจ้าหน้าที่
 *  - ผู้แจ้งเดิมแจ้งซ้ำจุดเดิม = อัปเดตรายงานเดิม (ไม่รกแผนที่)
 *  - คนต่างเครื่องแจ้งใกล้กันระดับใกล้เคียง = ยืนยันกันเอง
 *  - คนในพื้นที่โหวต ยังท่วม / น้ำลดแล้ว / ไม่ถูกต้อง
 *  - เฉพาะรายงานที่น่าเชื่อถือ (ยืนยันแล้ว) ถูกส่งเข้าระบบเตือนจุดเสี่ยง
 */
class WaterReportService
{
    /** รัศมีที่ถือว่ารายงานสองอันยืนยันกัน */
    public const CORROBORATE_M = 300;

    public function __construct(protected HelpRequestService $cases) {}

    public static function rules(bool $staff = false): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:5,21'],
            'lng' => ['required', 'numeric', 'between:97,106'],
            'accuracy_m' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'level' => ['required', 'integer', 'between:1,6'],
            'trend' => ['nullable', Rule::in(array_keys(ReportOptions::TRENDS))],
            'place_type' => ['nullable', Rule::in(array_keys(ReportOptions::PLACES))],
            'note' => ['nullable', 'string', 'max:500'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['image', 'max:8192'],
            'reporter_name' => ['nullable', 'string', 'max:120'],
            'reporter_phone' => ['nullable', 'string', 'regex:/^[0-9+\s\-]{9,15}$/'],
        ];
    }

    public static function attributes(): array
    {
        return ['lat' => 'ตำแหน่ง', 'level' => 'ระดับน้ำ', 'photos.*' => 'รูป', 'reporter_phone' => 'เบอร์โทร', 'note' => 'รายละเอียด'];
    }

    /**
     * @param  array{source?: string, user?: ?User, team_id?: ?int, device_hash?: ?string, ip_hash?: ?string, photos?: UploadedFile[]}  $meta
     * @return array{0: WaterReport, 1: bool} [รายงาน, true ถ้าเป็นการอัปเดตรายงานเดิม]
     */
    public function submit(Province $province, array $data, array $meta = []): array
    {
        $source = $meta['source'] ?? 'web';
        $hours = (int) Settings::get('report_expire_hours', $province->id);
        $photos = $this->storePhotos($meta['photos'] ?? []);
        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];

        // 1) ผู้แจ้งเดิม จุดเดิม ภายในชั่วโมง = อัปเดต
        if ($source === 'web' && ! empty($meta['device_hash'])) {
            $mine = WaterReport::inProvince($province->id)->where('device_hash', $meta['device_hash'])
                ->where('updated_at', '>=', now()->subMinutes(ReportOptions::SAME_SPOT_MIN))
                ->whereIn('status', ['published', 'pending'])->latest('id')->get()
                ->first(fn ($r) => Geo::distance($r->lat, $r->lng, $lat, $lng) <= ReportOptions::SAME_SPOT_M);
            if ($mine) {
                $mine->fill([
                    'level' => (int) $data['level'],
                    'trend' => $data['trend'] ?? $mine->trend,
                    'note' => $data['note'] ?? $mine->note,
                    'photos' => array_slice(array_merge($mine->photos ?? [], $photos), -6),
                    'expires_at' => now()->addHours($hours),
                ]);
                $mine->update_count++;
                $mine->save();
                $this->changed($mine, 'updated');

                return [$mine, true];
            }
        }

        [$districtId, $subdistrictId, $outside] = $this->cases->locate($province, $lat, $lng);
        $trusted = in_array($source, ['staff', 'team'], true);
        $status = $trusted ? 'published'
            : (($outside || Settings::get('report_premoderate', $province->id)) ? 'pending' : 'published');

        $report = DB::transaction(function () use ($province, $data, $meta, $source, $hours, $photos, $lat, $lng, $districtId, $subdistrictId, $outside, $trusted, $status) {
            $report = WaterReport::create([
                'province_id' => $province->id,
                'district_id' => $districtId,
                'subdistrict_id' => $subdistrictId,
                'lat' => $lat,
                'lng' => $lng,
                'accuracy_m' => $data['accuracy_m'] ?? null,
                'level' => (int) $data['level'],
                'trend' => $data['trend'] ?? null,
                'place_type' => $data['place_type'] ?? null,
                'note' => $data['note'] ?? null,
                'photos' => $photos ?: null,
                'reporter_name' => $data['reporter_name'] ?? ($meta['user'] ?? null)?->name,
                'reporter_phone' => ! empty($data['reporter_phone']) ? User::normalizePhone($data['reporter_phone']) : null,
                'device_hash' => $meta['device_hash'] ?? null,
                'ip_hash' => $meta['ip_hash'] ?? null,
                'source' => $source,
                'user_id' => ($meta['user'] ?? null)?->id,
                'team_id' => $meta['team_id'] ?? null,
                'status' => $status,
                'verified' => $trusted,
                'outside_province' => $outside,
                'hide_reason' => $outside ? 'อยู่นอกเขตจังหวัด' : null,
                'expires_at' => now()->addHours($hours),
            ]);

            if ($source === 'web' && $status === 'published') {
                $this->corroborate($report);
            }

            return $report;
        });

        $this->changed($report, 'created');

        return [$report->fresh(), false];
    }

    /** รายงานจากเครื่องอื่นในรัศมีใกล้กัน ระดับต่างกันไม่เกิน 1 ขั้น = ยืนยันกัน */
    protected function corroborate(WaterReport $report): void
    {
        $others = WaterReport::inProvince($report->province_id)->current()
            ->where('id', '!=', $report->id)
            ->whereBetween('lat', [$report->lat - 0.004, $report->lat + 0.004])
            ->whereBetween('lng', [$report->lng - 0.004, $report->lng + 0.004])
            ->where(fn ($q) => $q->whereNull('device_hash')->orWhere('device_hash', '!=', (string) $report->device_hash))
            ->whereBetween('level', [$report->level - 1, $report->level + 1])
            ->get()
            ->filter(fn ($r) => Geo::distance($r->lat, $r->lng, $report->lat, $report->lng) <= self::CORROBORATE_M)
            ->take(3);

        foreach ($others as $o) {
            $o->increment('confirm_count');
        }
        if ($others->isNotEmpty()) {
            $report->increment('confirm_count');
        }
    }

    /**
     * คนในพื้นที่โหวตรายงาน 1 เสียงต่อเครื่อง
     *
     * @return array{ok: bool, message: string}
     */
    public function vote(WaterReport $report, string $voterHash, string $kind): array
    {
        if ($report->status !== 'published' || $report->expires_at->isPast()) {
            return ['ok' => false, 'message' => 'รายงานนี้หมดอายุแล้ว'];
        }
        if ($report->device_hash && hash_equals($report->device_hash, $voterHash)) {
            return ['ok' => false, 'message' => 'แก้ไขรายงานของตัวเองโดยแจ้งระดับน้ำใหม่ที่จุดเดิม'];
        }

        $created = DB::transaction(function () use ($report, $voterHash, $kind) {
            $vote = WaterReportVote::firstOrCreate(
                ['water_report_id' => $report->id, 'voter_hash' => $voterHash],
                ['kind' => $kind, 'created_at' => now()],
            );
            if (! $vote->wasRecentlyCreated) {
                return false;
            }
            $r = WaterReport::lockForUpdate()->find($report->id);
            $hours = (int) Settings::get('report_expire_hours', $r->province_id);
            switch ($kind) {
                case 'confirm':
                    $r->confirm_count++;
                    // คนยืนยัน = ยังท่วม ต่ออายุออกไปครึ่งหนึ่งของอายุปกติ
                    $r->expires_at = $r->expires_at->max(now()->addHours(max(1, intdiv($hours, 2))));
                    break;
                case 'receded':
                    $r->recede_count++;
                    if ($r->recede_count >= ReportOptions::RECEDE_EXPIRE) {
                        $r->trend = 'falling';
                        $r->expires_at = $r->expires_at->min(now()->addHour());
                    }
                    break;
                case 'wrong':
                    $r->wrong_count++;
                    if ($r->wrong_count >= ReportOptions::WRONG_HIDE && ! $r->verified) {
                        $r->status = 'pending';
                        $r->hide_reason = 'มีผู้แจ้งว่าข้อมูลไม่ถูกต้อง '.$r->wrong_count.' คน';
                    }
                    break;
            }
            $r->save();
            $report->setRawAttributes($r->getAttributes(), true);

            return true;
        });

        if (! $created) {
            return ['ok' => false, 'message' => 'คุณให้ข้อมูลรายงานนี้ไปแล้ว'];
        }
        $this->changed($report, 'voted');

        return ['ok' => true, 'message' => 'ขอบคุณที่ช่วยยืนยันข้อมูล'];
    }

    /** ผู้ตรวจ: publish / verify / hide / reject */
    public function moderate(WaterReport $report, string $action, User $user, ?string $reason = null, bool $evaluate = true): string
    {
        $report->forceFill(['reviewed_by' => $user->id, 'reviewed_at' => now()]);
        switch ($action) {
            case 'publish':
                $report->forceFill(['status' => 'published', 'hide_reason' => null]);
                $msg = 'แสดงรายงานแล้ว';
                break;
            case 'verify':
                $report->forceFill(['status' => 'published', 'verified' => true, 'hide_reason' => null]);
                $msg = 'ยืนยันรายงานแล้ว ใช้ในระบบเตือน';
                break;
            case 'hide':
                $report->forceFill(['status' => 'hidden', 'hide_reason' => $reason ?: 'ผู้ตรวจซ่อน']);
                $msg = 'ซ่อนรายงานแล้ว';
                break;
            case 'reject':
                $report->forceFill(['status' => 'rejected', 'verified' => false, 'hide_reason' => $reason ?: 'ข้อมูลเท็จ']);
                $msg = 'ปฏิเสธรายงานแล้ว';
                break;
            default:
                throw new \InvalidArgumentException('action ไม่ถูกต้อง');
        }
        if (in_array($action, ['publish', 'verify'], true) && $report->expires_at->isPast()) {
            $report->expires_at = now()->addHours((int) Settings::get('report_expire_hours', $report->province_id));
        }
        $report->save();
        \App\Models\AuditLog::record('updated', $report, null, ['status' => $report->status, 'verified' => $report->verified], 'รายงานน้ำ #'.$report->id.': '.$msg);
        $this->changed($report, 'moderated', $evaluate);

        return $msg;
    }

    /** แหล่งระดับน้ำสำหรับ RiskEngine: รายงานที่แสดงอยู่และน่าเชื่อถือ */
    public static function samples(Province $province): Collection
    {
        return WaterReport::inProvince($province->id)->current()->trusted()
            ->get(['id', 'lat', 'lng', 'level', 'subdistrict_id', 'verified', 'source'])
            ->map(fn ($r) => [
                'lat' => $r->lat, 'lng' => $r->lng, 'level' => (int) $r->level,
                'label' => ($r->source === 'team' ? 'ทีมกู้ภัยรายงาน' : ($r->verified ? 'รายงานที่ยืนยันแล้ว' : 'รายงานประชาชน')).' #'.$r->id,
                'subdistrict_id' => $r->subdistrict_id, 'area' => false,
            ]);
    }

    /** แจ้งหน้าจอ realtime และให้ระบบเตือนทำงานทันทีเมื่อมีข้อมูลน่าเชื่อถือใหม่ */
    protected function changed(WaterReport $report, string $type, bool $evaluate = true): void
    {
        \Illuminate\Support\Facades\Cache::forget("public-map:{$report->province_id}");
        Live::signal($report->province_id, 'report.changed', ['id' => $report->id, 'type' => $type, 'level' => $report->level]);

        if ($evaluate && $report->status === 'published' && $report->isTrusted() && $report->level >= 2) {
            try {
                // ไม่ตรวจในคำขอนี้ ส่งเข้าคิวแบบรวบ (หลายรายงานในช่วงเดียวกัน ตรวจครั้งเดียว)
                \App\Jobs\EvaluateProvinceRisks::soon($report->province_id);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** @param  UploadedFile[]  $files */
    protected function storePhotos(array $files): array
    {
        $paths = [];
        foreach (array_slice($files, 0, 3) as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $paths[] = $file->store('reports/'.now()->format('Y/m'), 'public');
            }
        }

        return $paths;
    }
}
