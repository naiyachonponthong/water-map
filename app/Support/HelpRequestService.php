<?php

namespace App\Support;

use App\Models\HelpRequest;
use App\Models\HelpRequestEvent;
use App\Models\Province;
use App\Models\RunningNumber;
use App\Models\Subdistrict;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * กติกาหลักของเคสขอความช่วยเหลือ: สร้าง, กันซ้ำ, คะแนนความเร่งด่วน, เปลี่ยนสถานะ, รวมเคส
 */
class HelpRequestService
{
    /** กฎตรวจข้อมูลฟอร์ม (ประชาชนและเจ้าหน้าที่ใช้ชุดเดียวกัน) */
    public static function rules(bool $staff = false): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:5,21'],
            'lng' => ['required', 'numeric', 'between:97,106'],
            'location_source' => ['nullable', Rule::in(['gps', 'map', 'link', 'staff'])],
            'location_raw' => ['nullable', 'string', 'max:500'],
            'accuracy_m' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'address_text' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'floor_level' => ['nullable', 'integer', 'between:1,50'],
            'water_level' => ['required', 'integer', 'between:1,6'],
            'people_count' => ['required', 'integer', 'between:1,500'],
            'vulnerable' => ['nullable', 'array'],
            'vulnerable.*' => [Rule::in(array_keys(CaseOptions::VULNERABLE))],
            'needs' => [$staff ? 'nullable' : 'required', 'array', $staff ? 'min:0' : 'min:1'],
            'needs.*' => [Rule::in(array_keys(CaseOptions::NEEDS))],
            'needs_note' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['image', 'max:8192'],
            'requester_name' => ['required', 'string', 'max:120'],
            'requester_phone' => ['required', 'string', 'regex:/^[0-9+\s\-]{9,15}$/'],
            'on_behalf' => ['nullable', 'boolean'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'regex:/^[0-9+\s\-]{9,15}$/'],
            'source' => [$staff ? 'required' : 'prohibited', Rule::in(array_diff(array_keys(CaseOptions::SOURCES), ['web', 'proactive']))],
        ];
    }

    public static function attributes(): array
    {
        return [
            'lat' => 'ตำแหน่ง', 'lng' => 'ตำแหน่ง', 'water_level' => 'ระดับน้ำ', 'people_count' => 'จำนวนคน',
            'needs' => 'สิ่งที่ต้องการ', 'requester_name' => 'ชื่อผู้แจ้ง', 'requester_phone' => 'เบอร์โทร',
            'contact_phone' => 'เบอร์คนในพื้นที่', 'photos' => 'รูป', 'photos.*' => 'รูป', 'source' => 'ช่องทาง',
        ];
    }

    public static function phoneHash(string $phone): string
    {
        return hash_hmac('sha256', User::normalizePhone($phone), (string) config('app.key'));
    }

    /**
     * สร้างเคสใหม่ หรือแนบเข้ากับเคสเดิมถ้าเบอร์เดียวกันแจ้งซ้ำในช่วงเวลาที่กำหนด
     *
     * @return array{0: HelpRequest, 1: bool} [เคส, true ถ้าแนบเข้าเคสเดิม]
     */
    public function submit(Province $province, array $data, array $meta = []): array
    {
        $phone = User::normalizePhone($data['requester_phone']);
        $hash = self::phoneHash($phone);
        $windowHours = (int) Settings::get('duplicate_window_hours', $province->id);

        // 1) เบอร์เดียวกัน มีเคสเปิดอยู่ = คนเดิม แนบเข้าเคสเดิม
        $existing = HelpRequest::inProvince($province->id)->open()
            ->where('phone_hash', $hash)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->latest()->first();

        if ($existing && ($meta['source'] ?? 'web') === 'web') {
            $this->attachReport($existing, $data, $meta);

            return [$existing->fresh(), true];
        }

        $photos = $this->storePhotos($meta['photos'] ?? []);

        $req = DB::transaction(function () use ($province, $data, $meta, $phone, $hash, $photos, $existing) {
            [$districtId, $subdistrictId, $outside] = $this->locate($province, (float) $data['lat'], (float) $data['lng']);

            $req = new HelpRequest([
                'code' => $this->nextCode($province),
                'province_id' => $province->id,
                'district_id' => $districtId,
                'subdistrict_id' => $subdistrictId,
                'outside_province' => $outside,
                'lat' => round((float) $data['lat'], 7),
                'lng' => round((float) $data['lng'], 7),
                'location_source' => $data['location_source'] ?? 'gps',
                'location_raw' => $data['location_raw'] ?? null,
                'accuracy_m' => $data['accuracy_m'] ?? null,
                'address_text' => $data['address_text'] ?? null,
                'landmark' => $data['landmark'] ?? null,
                'floor_level' => $data['floor_level'] ?? null,
                'water_level' => (int) $data['water_level'],
                'people_count' => (int) $data['people_count'],
                'vulnerable' => array_values(array_unique($data['vulnerable'] ?? [])),
                'needs' => array_values(array_unique($data['needs'] ?? [])),
                'needs_note' => $data['needs_note'] ?? null,
                'photos' => $photos,
                'requester_name' => $data['requester_name'],
                'requester_phone' => $phone,
                'phone_hash' => $hash,
                'phone_last4' => substr($phone, -4),
                'on_behalf' => (bool) ($data['on_behalf'] ?? false),
                'contact_name' => $data['contact_name'] ?? null,
                'contact_phone' => ! empty($data['contact_phone']) ? User::normalizePhone($data['contact_phone']) : null,
                'source' => $meta['source'] ?? 'web',
                'status' => 'new',
                'created_by' => ($meta['user'] ?? null)?->id,
                'device_hash' => $meta['device_hash'] ?? null,
                'ip_hash' => isset($meta['ip']) ? hash('sha256', $meta['ip'].config('app.key')) : null,
            ]);
            // เจ้าหน้าที่คีย์เคสที่เบอร์ซ้ำ หรือมีเคสใกล้กัน: ไม่รวมเอง แต่ติดธงให้ตรวจ
            $req->possible_duplicate_of_id = $existing?->id ?? $this->findNearby($req)?->id;
            $this->applyPriority($req);
            $req->save();

            $this->event($req, 'created', [
                'user' => $meta['user'] ?? null,
                'actor' => isset($meta['user']) ? 'staff' : 'requester',
                'public' => true,
                'data' => ['source_label' => ' ทาง'.CaseOptions::SOURCES[$req->source][0]],
            ]);

            return $req;
        });

        return [$req, false];
    }

    /** ผู้แจ้งเดิมส่งข้อมูลซ้ำ: รวมข้อมูลเข้าเคสเดิม เลือกค่าที่ร้ายแรงกว่า */
    public function attachReport(HelpRequest $req, array $data, array $meta = []): void
    {
        $before = $req->only(['water_level', 'people_count', 'vulnerable', 'needs']);
        $photos = $this->storePhotos($meta['photos'] ?? []);

        $req->water_level = max((int) $req->water_level, (int) $data['water_level']);
        $req->people_count = max((int) $req->people_count, (int) $data['people_count']);
        $req->vulnerable = array_values(array_unique(array_merge($req->vulnerable ?? [], $data['vulnerable'] ?? [])));
        $req->needs = array_values(array_unique(array_merge($req->needs ?? [], $data['needs'] ?? [])));
        if (! empty($data['needs_note'])) {
            $req->needs_note = trim(($req->needs_note ? $req->needs_note."\n" : '').'['.now()->format('H:i').'] '.$data['needs_note']);
        }
        $req->photos = array_slice(array_merge($req->photos ?? [], $photos), 0, 9);
        $req->report_count++;
        $req->requester_updated_at = now();
        $this->applyPriority($req);
        $req->save();

        $this->event($req, 'update', [
            'actor' => 'requester',
            'public' => true,
            'note' => 'ผู้แจ้งส่งคำขอซ้ำ ระบบรวมข้อมูลเข้าคำขอเดิม',
            'data' => ['before' => $before, 'after' => $req->only(['water_level', 'people_count', 'vulnerable', 'needs'])],
        ]);
    }

    /** เคสเปิดอื่นที่อยู่ใกล้ (ต่างเบอร์) ไม่รวมอัตโนมัติ เพราะอาจเป็นเพื่อนบ้านคนละหลัง ให้เจ้าหน้าที่ตัดสิน */
    public function findNearby(HelpRequest $req): ?HelpRequest
    {
        $radius = (int) Settings::get('duplicate_radius_m', $req->province_id);
        $hours = (int) Settings::get('duplicate_window_hours', $req->province_id);
        $dLat = $radius / 111320;
        $dLng = $radius / (111320 * max(cos(deg2rad($req->lat)), 0.1));

        return HelpRequest::inProvince($req->province_id)->open()
            ->where('created_at', '>=', now()->subHours($hours))
            ->whereBetween('lat', [$req->lat - $dLat, $req->lat + $dLat])
            ->whereBetween('lng', [$req->lng - $dLng, $req->lng + $dLng])
            ->when($req->exists, fn ($q) => $q->where('id', '!=', $req->id))
            ->get()
            ->filter(fn ($o) => Geo::distance($req->lat, $req->lng, $o->lat, $o->lng) <= $radius)
            ->sortBy(fn ($o) => Geo::distance($req->lat, $req->lng, $o->lat, $o->lng))
            ->first();
    }

    /* ---------------- คะแนนความเร่งด่วน ---------------- */

    public static function score(HelpRequest $req, ?array $weights = null): int
    {
        $w = array_replace(
            config('floodthai.defaults.priority_weights'),
            $weights ?? (array) Settings::get('priority_weights', $req->province_id),
        );

        $score = (int) $req->water_level * $w['water_level'];

        $groups = collect($req->vulnerable ?? [])->map(fn ($k) => CaseOptions::VULNERABLE[$k][2] ?? null)->filter()->unique();
        if ($groups->contains('bedridden')) {
            $score += $w['bedridden'];
        }
        if ($groups->contains('child_elderly')) {
            $score += $w['child_elderly'];
        }
        if (in_array('food', $req->needs ?? [], true)) {
            $score += $w['no_food'];
        }
        // คนเยอะในจุดเดียว เพิ่มเล็กน้อย (สูงสุด +10)
        $score += min(10, max(0, (int) $req->people_count - 3));

        // รอเกิน 1 ชม. ยังไม่มีทีม
        if (in_array($req->status, CaseOptions::WAITING, true) && $req->created_at) {
            $hours = (int) floor($req->created_at->diffInMinutes(now(), true) / 60);
            $score += $hours * $w['per_hour_waiting'];
        }

        return min($score, 999);
    }

    public static function levelFor(HelpRequest $req, int $score): string
    {
        // น้ำเกินอกขึ้นไปและมีคนช่วยตัวเองไม่ได้ = วิกฤตเสมอ
        if ((int) $req->water_level >= 5 && $req->hasVulnerable('bedridden', 'disabled', 'infant')) {
            return 'critical';
        }

        return match (true) {
            $score >= 80 => 'critical',
            $score >= 50 => 'high',
            $score >= 25 => 'medium',
            default => 'low',
        };
    }

    public function applyPriority(HelpRequest $req): void
    {
        $req->priority_score = self::score($req);
        if (! $req->priority_locked) {
            $req->priority = self::levelFor($req, $req->priority_score);
        }
    }

    /** คำนวณใหม่ (งานตั้งเวลา) คืน true ถ้าเปลี่ยน */
    public function recalc(HelpRequest $req): bool
    {
        $old = [$req->priority_score, $req->priority];
        $this->applyPriority($req);
        if ($old === [$req->priority_score, $req->priority]) {
            return false;
        }
        $req->saveQuietly();

        return true;
    }

    /* ---------------- เปลี่ยนสถานะ / รวม / บันทึก ---------------- */

    public function changeStatus(HelpRequest $req, string $to, ?User $user, ?string $note = null, array $extra = [], bool $publicNote = false): void
    {
        $from = $req->status;
        $actor = $user ? 'staff' : ($extra['_actor'] ?? 'requester');
        unset($extra['_actor']);
        if ($from === $to && empty($extra)) {
            return;
        }

        $req->fill($extra);
        $req->status = $to;
        if ($to === 'screening' && ! $req->screened_by) {
            $req->screened_by = $user?->id;
        }
        if (in_array($from, ['new', 'screening'], true) && ! in_array($to, ['new', 'screening'], true)) {
            $req->screened_by ??= $user?->id;
            $req->screened_at ??= now();
        }
        if (in_array($to, CaseOptions::FINAL, true)) {
            $req->closed_at ??= now();
        } else {
            $req->closed_at = null;
        }
        $this->applyPriority($req);
        $req->save();

        // เคสจบ (ผู้แจ้งกดปลอดภัย / ศูนย์ปิดเอง) ขณะที่ทีมยังมีงานค้าง: ปิดงานของทีมด้วย ทีมจะได้ว่างรับงานอื่น
        if (in_array($to, CaseOptions::FINAL, true) && $req->assignment_id) {
            $a = \App\Models\Assignment::find($req->assignment_id);
            if ($a && $a->isActive()) {
                $a->update(['status' => $to === 'rescued' ? 'done' : 'cancelled', 'done_at' => now(), 'note' => $note ?: $a->note]);
                app(DispatchService::class)->syncTeamStatus($a->team);
            }
        }

        $this->event($req, 'status', [
            'user' => $user,
            'actor' => $actor,
            'from' => $from,
            'to' => $to,
            'note' => $note,
            'public' => true,
            'data' => array_filter(['outcome' => $extra['outcome'] ?? null, 'people_rescued' => $extra['people_rescued'] ?? null, 'public_note' => $publicNote ?: null]),
        ]);
    }

    public function merge(HelpRequest $req, HelpRequest $into, ?User $user): void
    {
        DB::transaction(function () use ($req, $into, $user) {
            $this->changeStatus($req, 'merged', $user, 'รวมเข้ากับเคส '.$into->code, [
                'duplicate_of_id' => $into->id,
                'outcome' => 'duplicate',
            ]);

            $into->people_count = max($into->people_count, $req->people_count);
            $into->water_level = max($into->water_level, $req->water_level);
            $into->vulnerable = array_values(array_unique(array_merge($into->vulnerable ?? [], $req->vulnerable ?? [])));
            $into->needs = array_values(array_unique(array_merge($into->needs ?? [], $req->needs ?? [])));
            $into->photos = array_slice(array_merge($into->photos ?? [], $req->photos ?? []), 0, 9);
            $into->report_count += $req->report_count;
            if ($into->possible_duplicate_of_id === $req->id) {
                $into->possible_duplicate_of_id = null;
            }
            $this->applyPriority($into);
            $into->save();

            $this->event($into, 'merged', [
                'user' => $user,
                'note' => 'รวมเคส '.$req->code.' ('.$req->requester_name.') เข้ามา',
                'data' => ['merged_id' => $req->id, 'merged_code' => $req->code],
            ]);
        });
    }

    public function event(HelpRequest $req, string $type, array $opts = []): HelpRequestEvent
    {
        $user = $opts['user'] ?? null;

        return $req->events()->create([
            'user_id' => $user?->id,
            'actor' => $opts['actor'] ?? ($user ? 'staff' : 'system'),
            'type' => $type,
            'from_status' => $opts['from'] ?? null,
            'to_status' => $opts['to'] ?? null,
            'note' => $opts['note'] ?? null,
            'data' => $opts['data'] ?? null,
            'public' => $opts['public'] ?? false,
            'created_at' => now(),
        ]);
    }

    /* ---------------- ภายใน ---------------- */

    /** FL24-6910-0001 = จังหวัด 24, ปี พ.ศ. 69 เดือน 10, ลำดับ 1 ของเดือนนั้นในจังหวัด */
    protected function nextCode(Province $province): string
    {
        $yymm = substr((string) (now()->year + 543), -2).now()->format('m');
        $seq = RunningNumber::next("case:{$province->code}:$yymm");

        return sprintf('FL%s-%s-%04d', $province->code, $yymm, $seq);
    }

    /** หาอำเภอ/ตำบลจากพิกัด และบอกว่าอยู่นอกจังหวัดหรือไม่ (ถ้ามีขอบเขตครบ) */
    public function locate(Province $province, float $lat, float $lng): array
    {
        $sub = Subdistrict::locate($province->id, $lat, $lng);
        if ($sub) {
            return [$sub->district_id, $sub->id, false];
        }

        $district = $province->districts()->whereNotNull('boundary')->get()->first(fn ($d) => $d->containsPoint($lat, $lng));
        $hasBoundaries = $province->districts()->whereNotNull('boundary')->exists();

        return [$district?->id, null, $hasBoundaries && ! $district];
    }

    /** @param  UploadedFile[]  $files */
    protected function storePhotos(array $files): array
    {
        $paths = [];
        foreach (array_slice($files, 0, 3) as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $paths[] = $file->store('help/'.now()->format('Y/m'), 'public');
            }
        }

        return $paths;
    }
}
