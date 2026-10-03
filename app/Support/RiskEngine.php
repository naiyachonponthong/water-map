<?php

namespace App\Support;

use App\Models\AreaWaterLevel;
use App\Models\HelpRequest;
use App\Models\Province;
use App\Models\RiskPoint;
use App\Models\VulnerableHousehold;
use Illuminate\Support\Collection;

/**
 * เตือนล่วงหน้าจากข้อมูลที่มี:
 *  - จุดเสี่ยงที่มีน้ำท่วมถึงในรัศมี/โซน → สถานะ "ถูกคุกคาม"
 *  - ครัวเรือนเปราะบางในพื้นที่ที่น้ำถึงเกณฑ์ → เปิดเคส "ตรวจเยี่ยมเชิงรุก" ให้อัตโนมัติ
 * แหล่งระดับน้ำ: เคสขอความช่วยเหลือ (12 ชม.), ระดับน้ำรายตำบลที่ศูนย์ประกาศ, และแหล่งที่เพิ่มในเฟสถัดไป
 */
class RiskEngine
{
    /** @var array<string, callable(Province): Collection> แหล่งระดับน้ำเพิ่มเติม (เช่น รายงานจากประชาชน) */
    protected static array $extraSources = [];

    public function __construct(protected HelpRequestService $cases) {}

    /** เพิ่มแหล่งระดับน้ำ (ตั้งชื่อ key ไว้ ลงทะเบียนซ้ำก็ไม่ซ้อน) */
    public static function addSource(string $key, callable $source): void
    {
        static::$extraSources[$key] = $source;
    }

    /**
     * จุดข้อมูลระดับน้ำ: [lat, lng, level, label, subdistrict_id]
     */
    public function samples(Province $province): Collection
    {
        $samples = HelpRequest::inProvince($province->id)
            ->whereNotIn('status', ['merged', 'cancelled'])
            // เคสเชิงรุกที่ระบบเปิดเอง ไม่นับเป็นแหล่งข้อมูล (กันการลามต่อเป็นทอดๆ ตามรัศมี)
            ->where('source', '!=', 'proactive')
            ->where(fn ($q) => $q->whereIn('status', CaseOptions::OPEN)->orWhere('updated_at', '>=', now()->subHours(12)))
            ->where(fn ($q) => $q->whereNull('outcome')->orWhere('outcome', '!=', 'fake'))
            ->get(['id', 'code', 'lat', 'lng', 'water_level', 'subdistrict_id'])
            ->map(fn ($c) => ['lat' => $c->lat, 'lng' => $c->lng, 'level' => (int) $c->water_level, 'label' => 'เคส '.$c->code, 'subdistrict_id' => $c->subdistrict_id, 'area' => false]);

        $areas = AreaWaterLevel::where('province_id', $province->id)->current()->with('subdistrict:id,name_th,code,center_lat,center_lng')->get()
            ->map(fn ($a) => ['lat' => $a->subdistrict?->center_lat, 'lng' => $a->subdistrict?->center_lng, 'level' => (int) $a->level, 'label' => 'ศูนย์ประกาศ '.$a->subdistrict?->shortName(), 'subdistrict_id' => $a->subdistrict_id, 'area' => true]);

        $all = $samples->concat($areas);
        foreach (static::$extraSources as $source) {
            $all = $all->concat($source($province));
        }

        return $all->values();
    }

    /** ระดับน้ำสูงสุดที่กระทบจุดนี้: [level, เหตุผล] */
    public function levelAt(Collection $samples, float $lat, float $lng, ?int $subdistrictId, int $radius, ?RiskPoint $zoneOf = null): array
    {
        $best = [0, null];
        foreach ($samples as $s) {
            $hit = false;
            if ($s['area']) {
                $hit = $subdistrictId && $s['subdistrict_id'] === $subdistrictId;
            } elseif ($s['lat'] !== null) {
                if (! empty($s['radius'])) {
                    // แหล่งที่มีรัศมีอิทธิพลของตัวเอง (เช่น สถานีวัดน้ำ)
                    $hit = Geo::distance($lat, $lng, $s['lat'], $s['lng']) <= max($s['radius'], $zoneOf ? 0 : $radius)
                        || ($zoneOf && $zoneOf->covers($s['lat'], $s['lng']));
                } else {
                    $hit = $zoneOf ? $zoneOf->covers($s['lat'], $s['lng']) : Geo::distance($lat, $lng, $s['lat'], $s['lng']) <= $radius;
                }
            }
            if ($hit && $s['level'] > $best[0]) {
                $best = [$s['level'], $s['label']];
            }
        }

        return $best;
    }

    public function evaluate(Province $province): array
    {
        $samples = $this->samples($province);
        $report = ['threatened' => 0, 'cleared' => 0, 'cases' => 0];

        // 1) จุดเสี่ยง
        RiskPoint::inProvince($province->id)->live()->get()->each(function (RiskPoint $p) use ($samples, &$report) {
            [$level, $reason] = $this->levelAt($samples, $p->lat, $p->lng, $p->subdistrict_id, $p->radius(), $p);
            if ($level >= $p->trigger_level) {
                $changed = $p->status !== 'threatened' || $p->threat_level !== $level;
                $p->forceFill([
                    'status' => 'threatened',
                    'threatened_at' => $p->status === 'threatened' ? $p->threatened_at : now(),
                    'threat_level' => $level,
                    'threat_reason' => 'น้ำ'.CaseOptions::waterLevel($level).' จาก'.$reason,
                ])->saveQuietly();
                $report['threatened'] += (int) $changed;
                // จุดเสี่ยงสูงขึ้นประกาศเตือนภัยด้วย
                if ($changed && $p->severity === 'high') {
                    app(AlertService::class)->raise($p->province_id, 'risk:'.$p->id, 'risk', $level >= 4 ? 'critical' : 'warning',
                        'จุดเสี่ยง'.$p->name.' ถูกน้ำคุกคาม', $p->threat_reason.($p->description ? '. '.$p->description : ''),
                        ['lat' => $p->lat, 'lng' => $p->lng, 'is_public' => $p->is_public, 'district_ids' => $p->district_id ? [$p->district_id] : null]);
                }
            } elseif ($p->status === 'threatened' && $level === 0 && $p->threatened_at?->lt(now()->subHours(6))) {
                // ไม่มีรายงานน้ำแล้ว และขึ้นเตือนมานานกว่า 6 ชม. กลับเป็นปกติ
                $p->forceFill(['status' => 'normal', 'threat_level' => null, 'threat_reason' => null])->saveQuietly();
                $report['cleared']++;
                app(AlertService::class)->resolveKey($p->province_id, 'risk:'.$p->id);
            }
        });

        // 2) ครัวเรือนเปราะบาง
        $trigger = (int) Settings::get('household_trigger_level', $province->id);
        $radius = (int) Settings::get('risk_alert_radius_m', $province->id);
        VulnerableHousehold::inProvince($province->id)->where('is_active', true)->with('subdistrict')->get()
            ->each(function (VulnerableHousehold $h) use ($samples, $trigger, $radius, $province, &$report) {
                [$lat, $lng] = $this->householdPosition($h);
                if ($lat === null) {
                    return;
                }
                [$level, $reason] = $this->levelAt($samples, $lat, $lng, $h->subdistrict_id, $radius);
                if ($level >= $trigger && $this->canOpenProactive($h)) {
                    $this->openProactiveCase($province, $h, $level, 'น้ำ'.CaseOptions::waterLevel($level).' จาก'.$reason);
                    $report['cases']++;
                }
            });

        if ($report['threatened'] || $report['cleared'] || $report['cases']) {
            Live::signal($province->id, 'risk.changed', $report);
        }

        return $report;
    }

    /** ไม่เปิดซ้ำ ถ้ามีเคสเปิดอยู่ หรือเพิ่งปิด/เพิ่งเยี่ยมไปในช่วง cooldown */
    public function canOpenProactive(VulnerableHousehold $h): bool
    {
        $cooldown = now()->subHours((int) Settings::get('proactive_cooldown_hours', $h->province_id));
        if ($h->last_checked_at && $h->last_checked_at->gt($cooldown) && $h->last_check_status !== 'needs_help') {
            return false;
        }
        $last = HelpRequest::where('household_id', $h->id)->latest('id')->first();

        return ! $last || (! $last->isOpen() && ($last->closed_at ?? $last->updated_at)->lt($cooldown));
    }

    public function openProactiveCase(Province $province, VulnerableHousehold $h, int $level, string $reason, ?\App\Models\User $user = null): HelpRequest
    {
        [$lat, $lng] = $this->householdPosition($h);
        $severe = (bool) array_intersect($h->conditions ?? [], ['bedridden', 'oxygen', 'dialysis', 'wheelchair']);
        $phone = $h->phone ?: $h->caretaker_phone ?: '0000000000';

        [$case] = $this->cases->submit($province, [
            'lat' => $lat,
            'lng' => $lng,
            'location_source' => 'staff',
            'address_text' => $h->address,
            'water_level' => max(1, $level),
            'people_count' => max(1, $h->members),
            'vulnerable' => $h->caseVulnerable(),
            'needs' => $severe || $level >= 3 ? ['evacuate', 'medicine'] : ['medicine'],
            'needs_note' => trim('ตรวจเยี่ยมเชิงรุก: '.implode(', ', $h->conditionLabels()).'. '.$reason.($h->note ? "\n".$h->note : '')),
            'requester_name' => $h->head_name,
            'requester_phone' => $phone,
            'contact_name' => $h->caretaker_name,
            'contact_phone' => $h->caretaker_phone,
            'on_behalf' => (bool) $h->caretaker_name,
        ], ['source' => 'proactive', 'user' => $user]);

        $case->forceFill(['household_id' => $h->id])->saveQuietly();
        // ข้อมูลจากทะเบียนถือว่าคัดกรองแล้ว เข้าคิวรอทีมเลย
        $this->cases->changeStatus($case, 'queued', $user, 'ระบบเปิดเคสตรวจเยี่ยมเชิงรุกจากทะเบียนครัวเรือนเปราะบาง', ['_actor' => 'system']);
        $h->forceFill(['open_case_id' => $case->id])->saveQuietly();

        return $case;
    }

    /** บันทึกผลการเยี่ยมบ้าน (จากแอปภาคสนามหรือหลังบ้าน) */
    public function recordCheck(VulnerableHousehold $h, string $status, ?\App\Models\User $user, ?int $teamId = null, ?string $note = null): void
    {
        $h->checks()->create(['user_id' => $user?->id, 'team_id' => $teamId, 'status' => $status, 'note' => $note, 'created_at' => now()]);
        $h->forceFill(['last_checked_at' => now(), 'last_check_status' => $status, 'last_checked_by' => $user?->id])->saveQuietly();

        if ($status === 'needs_help' && ! ($h->openCase && $h->openCase->isOpen())) {
            [$lat, $lng] = $this->householdPosition($h);
            $level = $lat !== null
                ? $this->levelAt($this->samples($h->province), $lat, $lng, $h->subdistrict_id, (int) Settings::get('risk_alert_radius_m', $h->province_id))[0]
                : 0;
            $this->openProactiveCase($h->province, $h, max(2, $level), 'ผลเยี่ยมบ้าน: ต้องการความช่วยเหลือ'.($note ? '. '.$note : ''), $user);
        }
    }

    /** พิกัดบ้าน ถ้าไม่มีใช้จุดกลางตำบล */
    public function householdPosition(VulnerableHousehold $h): array
    {
        if ($h->lat !== null && $h->lng !== null) {
            return [$h->lat, $h->lng];
        }

        return [$h->subdistrict?->center_lat, $h->subdistrict?->center_lng];
    }
}
