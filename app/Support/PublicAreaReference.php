<?php

namespace App\Support;

use App\Models\Province;

/** Read-only reference geography; never overwrites emergency-response areas. */
class PublicAreaReference
{
    private function read(string $file): array
    {
        return json_decode(file_get_contents(resource_path('data/'.$file)), true, 512, JSON_THROW_ON_ERROR);
    }

    public function boundary(Province $province): ?array
    {
        foreach ($this->read('thailand-provinces.geojson')['features'] as $feature) {
            if (($feature['properties']['shapeISO'] ?? '') === 'TH-'.$province->code) {
                $feature['properties'] = ['name' => $province->name_th, 'code' => $province->code];

                return $feature;
            }
        }

        return null;
    }

    public function areas(Province $province): array
    {
        $reference = collect($this->read('area-provinces.json'))->first(fn ($p) => $p['name']['th'] === $province->name_th);
        if (! $reference) {
            return [];
        }
        $districts = collect($this->read('area-districts.json'))->filter(fn ($d) => $d['province_id'] === $reference['id'] && empty($d['deleted_at']));
        $subs = collect($this->read('area-subdistricts.json'))->filter(fn ($s) => empty($s['deleted_at']))->groupBy('district_id');

        return $districts->map(fn ($d) => ['code' => (string) $d['id'], 'name' => $d['prefix']['th'].$d['name']['th'],
            'subdistricts' => collect($subs[$d['id']] ?? [])->map(fn ($s) => ['code' => (string) $s['id'],
                'name' => $s['prefix']['th'].$s['name']['th'], 'center' => is_numeric($s['lat']) && is_numeric($s['long']) ? [(float) $s['lat'], (float) $s['long']] : null])->values()->all(),
        ])->values()->all();
    }
}
