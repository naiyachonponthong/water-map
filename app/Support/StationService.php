<?php

namespace App\Support;

use App\Models\Province;
use App\Models\StationReading;
use App\Models\User;
use App\Models\WaterStation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * สถานีวัดระดับน้ำ: บันทึกค่า คำนวณสถานะ/แนวโน้ม เตือนภัย และเป็นแหล่งข้อมูลของระบบเตือนจุดเสี่ยง
 */
class StationService
{
    public function __construct(protected AlertService $alerts) {}

    /** บันทึกค่าระดับน้ำ (ค่าเดิมเวลาเดิมจะถูกแทนที่) */
    public function record(WaterStation $station, float $value, ?Carbon $at = null, string $source = 'manual', ?User $user = null): StationReading
    {
        $at = ($at ?? now())->copy()->second(0);
        $reading = StationReading::updateOrCreate(
            ['water_station_id' => $station->id, 'measured_at' => $at],
            ['value' => round($value, 2), 'source' => $source, 'user_id' => $user?->id, 'created_at' => now()],
        );

        if (! $station->last_at || $at->gte($station->last_at)) {
            $station->last_value = round($value, 2);
            $station->last_at = $at;
            $station->trend_per_hour = $this->trend($station, $at, $value);
            $station->status = $station->statusFor($station->last_value);
            $station->save();
            $this->syncAlert($station);
        }

        return $reading;
    }

    /** แนวโน้ม ม./ชม. เทียบค่าที่ใกล้ 3 ชม. ก่อนหน้าที่สุด (ช่วง 1-6 ชม.) */
    protected function trend(WaterStation $station, Carbon $at, float $value): ?float
    {
        $prev = StationReading::where('water_station_id', $station->id)
            ->whereBetween('measured_at', [$at->copy()->subHours(6), $at->copy()->subMinutes(50)])
            ->get()
            ->sortBy(fn ($r) => abs($r->measured_at->diffInMinutes($at->copy()->subHours(3), true)))
            ->first();
        if (! $prev) {
            return null;
        }
        $hours = max(0.5, $prev->measured_at->diffInMinutes($at, true) / 60);

        return round(($value - $prev->value) / $hours, 3);
    }

    /** สร้าง/อัปเดต/ปิดประกาศเตือนของสถานีตามสถานะ */
    public function syncAlert(WaterStation $station): void
    {
        $key = 'station:'.$station->id;
        if (! in_array($station->status, ['watch', 'warning', 'critical'], true)) {
            $this->alerts->resolveKey($station->province_id, $key);

            return;
        }

        $toBank = $station->toBank();
        $body = collect([
            'ระดับน้ำ '.number_format($station->last_value, 2).' '.$station->unit,
            $toBank !== null ? ($toBank >= 0 ? 'สูงกว่าตลิ่ง '.number_format($toBank, 2).' ม.' : 'ต่ำกว่าตลิ่ง '.number_format(abs($toBank), 2).' ม.') : null,
            $station->trendLabel(),
            $station->river ? 'ลำน้ำ'.$station->river : null,
        ])->filter()->implode(' · ');

        $this->alerts->raise($station->province_id, $key, 'station', $station->status,
            'สถานี'.$station->name.' ระดับ'.$station->statusLabel(), $body, [
                'lat' => $station->lat, 'lng' => $station->lng, 'is_public' => $station->is_public,
                'district_ids' => $station->district_id ? [$station->district_id] : null,
            ]);
    }

    /** สถานีที่ไม่มีค่าใหม่เกินกำหนด = ขาดการติดต่อ */
    public function markOffline(Province $province): int
    {
        $n = 0;
        WaterStation::inProvince($province->id)->where('is_active', true)
            ->whereNotIn('status', ['offline', 'unknown'])
            ->where('last_at', '<', now()->subHours(StationOptions::OFFLINE_HOURS))
            ->get()->each(function (WaterStation $s) use (&$n) {
                $s->update(['status' => 'offline']);
                $this->alerts->resolveKey($s->province_id, 'station:'.$s->id);
                $n++;
            });

        return $n;
    }

    /** ดึงค่าจาก JSON API ที่ตั้งไว้ */
    public function fetch(WaterStation $station): bool
    {
        if ($station->fetch_mode !== 'json' || ! $station->fetch_url || ! $station->value_path) {
            return false;
        }
        try {
            $res = SafeUrl::get($station->fetch_url, 12);
            if (! $res->successful()) {
                throw new \RuntimeException('HTTP '.$res->status());
            }
            $json = $res->json();
            $raw = data_get($json, $station->value_path);
            if (! is_numeric($raw)) {
                throw new \RuntimeException('ไม่พบค่าตัวเลขที่ '.$station->value_path);
            }
            $time = $station->time_path ? data_get($json, $station->time_path) : null;
            $at = $time ? Carbon::parse($time, config('app.timezone')) : now();
            if ($at->isFuture()) {
                $at = now();
            }
            $this->record($station, (float) $raw + (float) $station->value_offset, $at, 'json');
            $station->forceFill(['fetch_error' => null, 'fetched_at' => now()])->save();

            return true;
        } catch (Throwable $e) {
            $station->forceFill(['fetch_error' => mb_substr($e->getMessage(), 0, 250), 'fetched_at' => now()])->save();

            return false;
        }
    }

    /**
     * นำเข้าค่าจาก CSV: เวลา,ค่า (หัวคอลัมน์ไทย/อังกฤษ)
     *
     * @return array{created: int, errors: array<int, string>}
     */
    public function importCsv(WaterStation $station, string $raw, ?User $user = null): array
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = (string) @iconv('TIS-620', 'UTF-8//IGNORE', $raw);
        }
        $rows = array_values(array_filter(array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($raw)))));
        $report = ['created' => 0, 'errors' => []];
        if (! $rows) {
            return $report;
        }
        $head = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $rows[0]);
        $ti = $this->col($head, ['time', 'datetime', 'measured_at', 'เวลา', 'วันเวลา', 'วันที่']);
        $vi = $this->col($head, ['value', 'level', 'waterlevel', 'ค่า', 'ระดับน้ำ', 'ระดับ']);
        $start = ($ti !== null && $vi !== null) ? 1 : 0;
        $ti ??= 0;
        $vi ??= 1;

        $records = [];
        foreach (array_slice($rows, $start) as $i => $row) {
            $t = trim((string) ($row[$ti] ?? ''));
            $v = str_replace(',', '', trim((string) ($row[$vi] ?? '')));
            if ($t === '' || ! is_numeric($v)) {
                $report['errors'][] = 'แถว '.($i + $start + 1).': ข้อมูลไม่ครบ';

                continue;
            }
            try {
                $at = $this->parseThaiTime($t);
            } catch (Throwable) {
                $report['errors'][] = 'แถว '.($i + $start + 1).': อ่านเวลาไม่ได้ "'.$t.'"';

                continue;
            }
            $records[] = [$at, (float) $v];
        }

        // เรียงตามเวลา ให้แนวโน้มคำนวณถูก
        usort($records, fn ($a, $b) => $a[0] <=> $b[0]);
        foreach ($records as [$at, $v]) {
            $this->record($station, $v, $at, 'import', $user);
            $report['created']++;
        }

        return $report;
    }

    /** รองรับปี พ.ศ. เช่น 02/10/2569 14:00 */
    protected function parseThaiTime(string $t): Carbon
    {
        if (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})(?:\s+(\d{1,2}):(\d{2}))?~', $t, $m)) {
            $y = (int) $m[3] > 2400 ? (int) $m[3] - 543 : (int) $m[3];

            return Carbon::create($y, (int) $m[2], (int) $m[1], (int) ($m[4] ?? 0), (int) ($m[5] ?? 0), 0, config('app.timezone'));
        }

        return Carbon::parse($t, config('app.timezone'));
    }

    protected function col(array $head, array $names): ?int
    {
        foreach ($head as $i => $h) {
            if (in_array($h, $names, true)) {
                return $i;
            }
        }

        return null;
    }

    /** แหล่งระดับน้ำสำหรับ RiskEngine: สถานีที่ถึงเกณฑ์ ส่งผลในรัศมีของสถานี */
    public static function samples(Province $province): Collection
    {
        return WaterStation::inProvince($province->id)->where('is_active', true)->whereIn('status', ['watch', 'warning', 'critical'])->get()
            ->map(fn (WaterStation $s) => [
                'lat' => $s->lat, 'lng' => $s->lng, 'level' => StationOptions::STATUS[$s->status][3],
                'label' => 'สถานี'.$s->name.' ('.$s->statusLabel().')', 'subdistrict_id' => $s->subdistrict_id,
                'area' => false, 'radius' => (int) $s->influence_radius_m,
            ]);
    }
}
