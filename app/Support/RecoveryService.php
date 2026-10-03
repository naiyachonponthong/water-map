<?php

namespace App\Support;

use App\Models\AreaWaterLevel;
use App\Models\DamageClaim;
use App\Models\Evacuee;
use App\Models\HelpRequest;
use App\Models\Province;
use App\Models\RunningNumber;
use App\Models\User;
use App\Models\VulnerableHousehold;
use App\Models\WaterReport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * โหมดฟื้นฟูหลังน้ำลด: ยื่นคำร้อง → สำรวจ → พิจารณา → จ่าย
 * ระบบหาหลักฐานจากข้อมูลช่วงน้ำท่วมให้เอง ช่วยเจ้าหน้าที่จัดลำดับและลดคำร้องปลอม
 */
class RecoveryService
{
    public static function rules(): array
    {
        return [
            'head_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\s\-]{9,15}$/'],
            'address' => ['required', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:5,21', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:97,106', 'required_with:lat'],
            'members' => ['required', 'integer', 'between:1,50'],
            'tenure' => ['required', 'in:'.implode(',', array_keys(RecoveryOptions::TENURE))],
            'house_damage' => ['required', 'in:'.implode(',', array_keys(RecoveryOptions::HOUSE))],
            'water_level' => ['nullable', 'integer', 'between:1,6'],
            'flood_days' => ['nullable', 'integer', 'between:0,365'],
            'losses' => ['nullable', 'array'],
            'losses.*' => ['in:'.implode(',', array_keys(RecoveryOptions::LOSSES))],
            'crop_rai' => ['nullable', 'numeric', 'between:0,100000'],
            'livestock' => ['nullable', 'integer', 'between:0,1000000'],
            'note' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'max:8192'],
        ];
    }

    public static function attributes(): array
    {
        return ['head_name' => 'ชื่อ', 'phone' => 'เบอร์โทร', 'address' => 'ที่อยู่', 'house_damage' => 'ความเสียหายของบ้าน', 'members' => 'จำนวนคน', 'photos.*' => 'รูป'];
    }

    /**
     * @return array{0: DamageClaim, 1: bool} [คำร้อง, true ถ้าเบอร์นี้มีคำร้องค้างอยู่แล้ว]
     */
    public function submit(Province $province, array $data, array $meta = []): array
    {
        $phone = User::normalizePhone($data['phone']);
        $hash = HelpRequestService::phoneHash($phone);

        if ($existing = DamageClaim::inProvince($province->id)->where('phone_hash', $hash)->whereIn('status', RecoveryOptions::OPEN)->first()) {
            return [$existing, true];
        }

        $photos = $this->storePhotos($meta['photos'] ?? [], 'recovery');
        $claim = DB::transaction(function () use ($province, $data, $meta, $phone, $hash, $photos) {
            [$districtId, $subdistrictId] = isset($data['lat'], $data['lng']) && $data['lat'] !== null
                ? app(HelpRequestService::class)->locate($province, (float) $data['lat'], (float) $data['lng'])
                : [null, null];

            $claim = DamageClaim::create([
                'code' => $this->code($province),
                'province_id' => $province->id,
                'district_id' => $districtId,
                'subdistrict_id' => $subdistrictId,
                'head_name' => trim($data['head_name']),
                'phone' => $phone,
                'phone_hash' => $hash,
                'address' => $data['address'],
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'members' => (int) $data['members'],
                'tenure' => $data['tenure'],
                'house_damage' => $data['house_damage'],
                'water_level' => $data['water_level'] ?? null,
                'flood_days' => $data['flood_days'] ?? null,
                'losses' => array_values($data['losses'] ?? []) ?: null,
                'crop_rai' => $data['crop_rai'] ?? null,
                'livestock' => $data['livestock'] ?? null,
                'photos' => $photos ?: null,
                'note' => $data['note'] ?? null,
                'source' => $meta['source'] ?? 'web',
                'status' => 'submitted',
                'device_hash' => $meta['device_hash'] ?? null,
            ]);
            $this->event($claim, 'submitted', ($meta['source'] ?? 'web') === 'web' ? 'ยื่นคำร้องทางเว็บ' : 'เจ้าหน้าที่บันทึกคำร้อง', $meta['user'] ?? null);

            return $claim;
        });

        $this->evidence($claim);

        return [$claim->fresh(), false];
    }

    /** หลักฐานจากข้อมูลช่วงน้ำท่วม */
    public function evidence(DamageClaim $claim): array
    {
        $found = [];
        $case = HelpRequest::inProvince($claim->province_id)->where('phone_hash', $claim->phone_hash)->latest('id')->first();
        if ($case) {
            $found['help_request'] = $case->code;
            $claim->help_request_id = $case->id;
        }
        if (Evacuee::inProvince($claim->province_id)->where('phone_hash', $claim->phone_hash)->exists()) {
            $found['evacuee'] = true;
        }
        if ($claim->lat !== null) {
            $near = fn ($q) => $q->whereBetween('lat', [$claim->lat - 0.006, $claim->lat + 0.006])->whereBetween('lng', [$claim->lng - 0.006, $claim->lng + 0.006]);
            $hit = WaterReport::inProvince($claim->province_id)->where('status', '!=', 'rejected')->where($near)->get(['lat', 'lng', 'level'])
                ->concat(HelpRequest::inProvince($claim->province_id)->where(fn ($q) => $q->whereNull('outcome')->orWhere('outcome', '!=', 'fake'))->where($near)->get(['lat', 'lng', 'water_level']))
                ->filter(fn ($r) => Geo::distance($claim->lat, $claim->lng, $r->lat, $r->lng) <= 500);
            if ($hit->isNotEmpty()) {
                $found['water_report'] = $hit->count();
            }
        }
        if ($claim->subdistrict_id && AreaWaterLevel::where('subdistrict_id', $claim->subdistrict_id)->exists()) {
            $found['area_level'] = true;
        }
        if (VulnerableHousehold::inProvince($claim->province_id)->where('head_name', $claim->head_name)
            ->when($claim->subdistrict_id, fn ($q) => $q->where('subdistrict_id', $claim->subdistrict_id))->exists()) {
            $found['vulnerable'] = true;
        }

        $score = min(100, collect(array_keys($found))->sum(fn ($k) => RecoveryOptions::EVIDENCE[$k][1] ?? 0));
        $claim->forceFill(['evidence' => $found ?: null, 'evidence_score' => $score])->saveQuietly();

        return $found;
    }

    public function schedule(DamageClaim $claim, ?User $surveyor, User $by, ?string $note = null): void
    {
        $this->requireStatus($claim, ['submitted', 'surveying']);
        $claim->update(['status' => 'surveying', 'surveyor_id' => $surveyor?->id]);
        $this->event($claim, 'scheduled', 'นัดสำรวจ'.($surveyor ? ' โดย '.$surveyor->name : '').($note ? '. '.$note : ''), $by);
    }

    public function survey(DamageClaim $claim, array $data, User $by, array $photos = []): void
    {
        $this->requireStatus($claim, ['submitted', 'surveying', 'surveyed']);
        $paths = $this->storePhotos($photos, 'recovery/survey');
        $claim->fill([
            'status' => 'surveyed',
            'surveyor_id' => $by->id,
            'surveyed_at' => now(),
            'verified_damage' => $data['verified_damage'],
            'water_level' => $data['water_level'] ?? $claim->water_level,
            'losses' => array_values($data['losses'] ?? []) ?: $claim->losses,
            'crop_rai' => $data['crop_rai'] ?? $claim->crop_rai,
            'survey_note' => $data['survey_note'] ?? null,
            'survey_photos' => array_slice(array_merge($claim->survey_photos ?? [], $paths), 0, 10) ?: null,
        ]);
        $claim->suggested_amount = $this->suggest($claim);
        $claim->save();
        $this->event($claim, 'surveyed', 'สำรวจแล้ว: '.$claim->houseLabel($data['verified_damage']), $by);
    }

    public function approve(DamageClaim $claim, float $amount, User $by, ?string $note = null): void
    {
        $this->requireStatus($claim, ['surveyed', 'approved']);
        if ($amount < 0) {
            throw new RuntimeException('จำนวนเงินไม่ถูกต้อง');
        }
        $claim->update(['status' => 'approved', 'approved_amount' => round($amount, 2), 'approved_by' => $by->id, 'approved_at' => now(), 'reject_reason' => null]);
        $this->event($claim, 'approved', 'อนุมัติ '.number_format($amount, 2).' บาท'.($note ? '. '.$note : ''), $by);
    }

    public function pay(DamageClaim $claim, float $amount, ?string $ref, User $by): void
    {
        $this->requireStatus($claim, ['approved']);
        if ($amount <= 0 || $amount > (float) $claim->approved_amount) {
            throw new RuntimeException('จ่ายได้ไม่เกินยอดที่อนุมัติ '.number_format((float) $claim->approved_amount, 2).' บาท');
        }
        $claim->update(['status' => 'paid', 'paid_amount' => round($amount, 2), 'payment_ref' => $ref, 'paid_by' => $by->id, 'paid_at' => now()]);
        $this->event($claim, 'paid', 'จ่ายแล้ว '.number_format($amount, 2).' บาท', $by);
    }

    public function reject(DamageClaim $claim, string $reason, User $by): void
    {
        $this->requireStatus($claim, RecoveryOptions::OPEN);
        $claim->update(['status' => 'rejected', 'reject_reason' => $reason]);
        $this->event($claim, 'rejected', 'ไม่ผ่านเกณฑ์: '.$reason, $by);
    }

    /**
     * ยอดแนะนำจากอัตราที่จังหวัดตั้งไว้ (ตั้งตามระเบียบที่ใช้จริง) ถ้ายังไม่ได้ตั้งอัตรา คืน null
     */
    public function suggest(DamageClaim $claim): ?float
    {
        $rates = (array) Settings::get('recovery_rates', $claim->province_id);
        $house = (float) ($rates['house'][$claim->verified_damage ?? $claim->house_damage] ?? 0);
        $losses = collect($claim->losses ?? [])->sum(fn ($l) => (float) ($rates['loss'][$l] ?? 0));
        $crops = (float) ($rates['crop_per_rai'] ?? 0) * (float) ($claim->crop_rai ?? 0);
        $cap = (float) ($rates['cap'] ?? 0);
        $total = $house + $losses + $crops;
        if ($total <= 0) {
            return null;
        }

        return round($cap > 0 ? min($cap, $total) : $total, 2);
    }

    public function event(DamageClaim $claim, string $type, ?string $note, ?User $user, bool $public = true): void
    {
        $claim->events()->create(['type' => $type, 'note' => $note ? mb_substr($note, 0, 500) : null, 'user_id' => $user?->id, 'public' => $public, 'created_at' => now()]);
    }

    protected function requireStatus(DamageClaim $claim, array $allowed): void
    {
        if (! in_array($claim->status, $allowed, true)) {
            throw new RuntimeException('คำร้องสถานะ "'.$claim->statusLabel().'" ทำขั้นนี้ไม่ได้');
        }
    }

    protected function code(Province $province): string
    {
        $yymm = substr((string) (now()->year + 543), -2).now()->format('m');

        return sprintf('RC%s-%s-%04d', $province->code, $yymm, RunningNumber::next("rc:{$province->code}:$yymm"));
    }

    /** @param  UploadedFile[]  $files */
    protected function storePhotos(array $files, string $dir): array
    {
        $out = [];
        foreach (array_slice($files, 0, 5) as $f) {
            if ($f instanceof UploadedFile && $f->isValid()) {
                $out[] = $f->store($dir.'/'.now()->format('Y/m'), 'public');
            }
        }

        return $out;
    }
}
