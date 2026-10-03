<?php

namespace App\Support;

use App\Models\District;
use App\Models\Province;
use App\Models\RiskPoint;
use App\Models\Subdistrict;
use App\Models\User;
use App\Models\VulnerableHousehold;
use Illuminate\Http\UploadedFile;

/**
 * นำเข้าจุดเสี่ยง (CSV / KML / GeoJSON) และครัวเรือนเปราะบาง (CSV) จาก อปท. หรือ อสม.
 * CSV อ่านได้ทั้งหัวคอลัมน์ไทยและอังกฤษ รองรับไฟล์จาก Excel (UTF-8 มี BOM หรือ TIS-620)
 */
class RiskImporter
{
    public array $report = ['created' => 0, 'skipped' => 0, 'errors' => []];

    public function __construct(protected Province $province, protected ?User $user = null) {}

    /* ---------------- จุดเสี่ยง ---------------- */

    public function risks(UploadedFile $file, string $defaultType = 'flood_prone'): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $raw = (string) file_get_contents($file->getRealPath());

        match (true) {
            $ext === 'kml' => $this->risksFromKml($raw, $defaultType),
            in_array($ext, ['geojson', 'json'], true) => $this->risksFromGeoJson($raw, $defaultType),
            default => $this->risksFromCsv($raw, $defaultType),
        };

        return $this->report;
    }

    protected function risksFromCsv(string $raw, string $defaultType): void
    {
        foreach ($this->csv($raw) as $i => $row) {
            $lat = $this->num($row, ['lat', 'latitude', 'ละติจูด']);
            $lng = $this->num($row, ['lng', 'lon', 'longitude', 'ลองจิจูด']);
            $name = $this->str($row, ['name', 'ชื่อ', 'ชื่อจุด', 'ชื่อจุดเสี่ยง']);
            if (! $name || $lat === null || $lng === null) {
                $this->fail($i, 'ต้องมีชื่อ ละติจูด ลองจิจูด');

                continue;
            }
            $this->makeRisk([
                'name' => $name,
                'type' => $this->riskType($this->str($row, ['type', 'ประเภท'])) ?? $defaultType,
                'lat' => $lat, 'lng' => $lng,
                'radius_m' => $this->num($row, ['radius', 'radius_m', 'รัศมี']),
                'severity' => $this->severity($this->str($row, ['severity', 'ความรุนแรง', 'ระดับ'])),
                'description' => $this->str($row, ['description', 'รายละเอียด', 'หมายเหตุ']),
                'is_public' => ! in_array(mb_strtolower((string) $this->str($row, ['public', 'เผยแพร่'])), ['0', 'n', 'no', 'ไม่'], true),
            ]);
        }
    }

    protected function risksFromKml(string $raw, string $defaultType): void
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NONET);
        if (! $xml) {
            $this->report['errors'][] = 'อ่านไฟล์ KML ไม่ได้';

            return;
        }
        $xml->registerXPathNamespace('k', 'http://www.opengis.net/kml/2.2');
        $placemarks = $xml->xpath('//k:Placemark') ?: $xml->xpath('//Placemark') ?: [];

        foreach ($placemarks as $i => $pm) {
            $pm->registerXPathNamespace('k', 'http://www.opengis.net/kml/2.2');
            $name = trim((string) $pm->name) ?: 'จุดเสี่ยง '.($i + 1);
            $desc = trim(strip_tags((string) $pm->description)) ?: null;
            $point = $pm->xpath('.//k:Point/k:coordinates') ?: $pm->xpath('.//Point/coordinates');
            $poly = $pm->xpath('.//k:Polygon//k:outerBoundaryIs//k:coordinates') ?: $pm->xpath('.//Polygon//outerBoundaryIs//coordinates');

            if ($poly) {
                $ring = $this->kmlCoords((string) $poly[0]);
                if (count($ring) >= 4) {
                    $this->makeRisk(['name' => $name, 'type' => $defaultType, 'description' => $desc, 'lat' => 0, 'lng' => 0], ['type' => 'Polygon', 'coordinates' => [$ring]]);

                    continue;
                }
            }
            if ($point) {
                $c = $this->kmlCoords((string) $point[0])[0] ?? null;
                if ($c) {
                    $this->makeRisk(['name' => $name, 'type' => $defaultType, 'description' => $desc, 'lat' => $c[1], 'lng' => $c[0]]);

                    continue;
                }
            }
            $this->fail($i, 'ไม่มีพิกัดจุดหรือรูปหลายเหลี่ยม');
        }
    }

    protected function risksFromGeoJson(string $raw, string $defaultType): void
    {
        $json = json_decode($raw, true);
        $features = ($json['type'] ?? null) === 'FeatureCollection' ? ($json['features'] ?? []) : [$json];
        foreach ($features as $i => $f) {
            $g = $f['geometry'] ?? null;
            $p = $f['properties'] ?? [];
            $base = [
                'name' => $p['name'] ?? $p['ชื่อ'] ?? 'จุดเสี่ยง '.($i + 1),
                'type' => $this->riskType($p['type'] ?? $p['ประเภท'] ?? null) ?? $defaultType,
                'description' => $p['description'] ?? $p['รายละเอียด'] ?? null,
                'severity' => $this->severity($p['severity'] ?? null),
            ];
            match ($g['type'] ?? null) {
                'Point' => $this->makeRisk($base + ['lat' => (float) $g['coordinates'][1], 'lng' => (float) $g['coordinates'][0]]),
                'Polygon', 'MultiPolygon' => $this->makeRisk($base + ['lat' => 0, 'lng' => 0], $g),
                default => $this->fail($i, 'รองรับเฉพาะ Point และ Polygon'),
            };
        }
    }

    protected function makeRisk(array $data, ?array $zone = null): void
    {
        $risk = new RiskPoint(array_filter($data, fn ($v) => $v !== null) + [
            'province_id' => $this->province->id,
            'severity' => 'medium',
            'source' => 'import',
            'review' => 'approved',
            'created_by' => $this->user?->id,
        ]);
        if ($zone) {
            $risk->setZone($zone);
        }
        if (! $this->inThailand($risk->lat, $risk->lng)) {
            $this->fail(null, $risk->name.': พิกัดอยู่นอกประเทศไทย (สลับละติจูด/ลองจิจูดหรือไม่)');

            return;
        }
        $sub = Subdistrict::locate($this->province->id, $risk->lat, $risk->lng);
        $risk->subdistrict_id = $sub?->id;
        $risk->district_id = $sub?->district_id;
        $risk->save();
        $this->report['created']++;
    }

    /* ---------------- ครัวเรือนเปราะบาง ---------------- */

    public function households(UploadedFile $file): array
    {
        $raw = (string) file_get_contents($file->getRealPath());
        $subs = Subdistrict::where('province_id', $this->province->id)->get(['id', 'district_id', 'name_th']);
        $districts = District::where('province_id', $this->province->id)->get(['id', 'name_th']);

        foreach ($this->csv($raw) as $i => $row) {
            $name = $this->str($row, ['head_name', 'name', 'ชื่อ', 'ชื่อ-สกุล', 'ชื่อผู้ป่วย']);
            if (! $name) {
                $this->fail($i, 'ไม่มีชื่อ');

                continue;
            }
            $lat = $this->num($row, ['lat', 'ละติจูด']);
            $lng = $this->num($row, ['lng', 'lon', 'ลองจิจูด']);
            if ($lat !== null && ! $this->inThailand($lat, $lng)) {
                $lat = $lng = null;
            }

            $subName = $this->clean($this->str($row, ['subdistrict', 'ตำบล']), ['ตำบล', 'ต.']);
            $distName = $this->clean($this->str($row, ['district', 'อำเภอ']), ['อำเภอ', 'อ.']);
            $district = $distName ? $districts->firstWhere('name_th', $distName) : null;
            $sub = $subName ? $subs->first(fn ($s) => $s->name_th === $subName && (! $district || $s->district_id === $district->id)) : null;
            if (! $sub && $lat !== null) {
                $sub = Subdistrict::locate($this->province->id, $lat, $lng);
            }

            $address = $this->str($row, ['address', 'ที่อยู่', 'บ้านเลขที่']);
            $dup = VulnerableHousehold::where('province_id', $this->province->id)->where('head_name', $name)
                ->where(fn ($q) => $q->where('subdistrict_id', $sub?->id)->when($address, fn ($w) => $w->orWhere('address', $address)))
                ->exists();
            if ($dup) {
                $this->fail($i, "{$name} มีในทะเบียนแล้ว");

                continue;
            }

            $consent = in_array(mb_strtolower((string) $this->str($row, ['consent', 'ยินยอม'])), ['y', 'yes', '1', 'ใช่', 'ยินยอม'], true);

            VulnerableHousehold::create([
                'province_id' => $this->province->id,
                'district_id' => $sub?->district_id ?? $district?->id,
                'subdistrict_id' => $sub?->id,
                'lat' => $lat, 'lng' => $lng,
                'address' => $address,
                'head_name' => $name,
                'phone' => $this->phone($this->str($row, ['phone', 'เบอร์', 'เบอร์โทร', 'โทรศัพท์'])),
                'members' => max(1, (int) ($this->num($row, ['members', 'จำนวนคน', 'สมาชิก']) ?? 1)),
                'conditions' => $this->conditions($this->str($row, ['conditions', 'ภาวะ', 'กลุ่ม', 'ประเภท'])),
                'caretaker_name' => $this->str($row, ['caretaker_name', 'ผู้ดูแล', 'อสม']),
                'caretaker_phone' => $this->phone($this->str($row, ['caretaker_phone', 'เบอร์ผู้ดูแล', 'เบอร์อสม'])),
                'note' => $this->str($row, ['note', 'หมายเหตุ']),
                'consent_at' => $consent ? now() : null,
                'consent_by' => $consent ? $this->user?->id : null,
                'created_by' => $this->user?->id,
            ]);
            $this->report['created']++;
        }

        return $this->report;
    }

    /* ---------------- ตัวช่วย ---------------- */

    /** @return array<int, array<string, string>> แถวที่ key เป็นหัวคอลัมน์ตัวพิมพ์เล็ก */
    protected function csv(string $raw): array
    {
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = @iconv('TIS-620', 'UTF-8//IGNORE', $raw) ?: $raw;
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $lines = preg_split('/\r\n|\n|\r/', trim($raw));
        $header = array_map(fn ($h) => mb_strtolower(trim($h)), str_getcsv(array_shift($lines) ?? ''));
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line);
            $rows[] = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
        }

        return $rows;
    }

    protected function str(array $row, array $keys): ?string
    {
        foreach ($keys as $k) {
            $v = $row[mb_strtolower($k)] ?? null;
            if ($v !== null && trim($v) !== '') {
                return trim($v);
            }
        }

        return null;
    }

    protected function num(array $row, array $keys): ?float
    {
        $v = $this->str($row, $keys);

        return $v !== null && is_numeric(str_replace(',', '', $v)) ? (float) str_replace(',', '', $v) : null;
    }

    protected function phone(?string $v): ?string
    {
        $d = $v ? User::normalizePhone($v) : '';

        return strlen($d) >= 9 ? $d : null;
    }

    protected function clean(?string $v, array $prefixes): ?string
    {
        if (! $v) {
            return null;
        }
        foreach ($prefixes as $p) {
            if (str_starts_with($v, $p)) {
                return trim(mb_substr($v, mb_strlen($p)));
            }
        }

        return $v;
    }

    protected function riskType(?string $v): ?string
    {
        if (! $v) {
            return null;
        }
        if (array_key_exists($v, RiskOptions::TYPES)) {
            return $v;
        }
        foreach (RiskOptions::TYPES as $k => [$label]) {
            if (str_contains($label, $v) || str_contains($v, explode('/', $label)[0])) {
                return $k;
            }
        }

        return null;
    }

    protected function severity(?string $v): string
    {
        return match (mb_strtolower((string) $v)) {
            'high', 'สูง', '3' => 'high',
            'low', 'ต่ำ', '1' => 'low',
            default => 'medium',
        };
    }

    protected function conditions(?string $v): array
    {
        if (! $v) {
            return ['other'];
        }
        $out = [];
        foreach (preg_split('/[,;|]/u', $v) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (array_key_exists($part, RiskOptions::CONDITIONS)) {
                $out[] = $part;

                continue;
            }
            $hit = collect(RiskOptions::CONDITIONS)->search(fn ($c) => str_contains($c[0], $part) || str_contains($part, explode('/', $c[0])[0]));
            $out[] = $hit ?: 'other';
        }

        return array_values(array_unique($out));
    }

    protected function kmlCoords(string $text): array
    {
        return collect(preg_split('/\s+/', trim($text)))->filter()
            ->map(fn ($t) => array_map('floatval', array_slice(explode(',', $t), 0, 2)))
            ->filter(fn ($c) => count($c) === 2)->values()->all();
    }

    protected function inThailand(?float $lat, ?float $lng): bool
    {
        return $lat !== null && $lng !== null && $lat >= 5 && $lat <= 21 && $lng >= 97 && $lng <= 106;
    }

    protected function fail(?int $i, string $msg): void
    {
        $this->report['skipped']++;
        $this->report['errors'][] = ($i !== null ? 'แถว '.($i + 2).': ' : '').$msg;
    }
}
