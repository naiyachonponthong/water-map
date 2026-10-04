<?php

namespace App\Support;

use App\Models\Province;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Saves observed readings, not a reconstructed or predicted history. */
class PublicWaterHistory
{
    public function available(): bool
    {
        return Schema::hasTable('public_water_readings');
    }

    public function record(Province $province, array $stations): void
    {
        if (! $stations || ! $this->available()) {
            return;
        }
        $rows = [];
        foreach ($stations as $station) {
            if ($station['value'] === null || Carbon::parse($station['measured_at'])->lt(now()->subDays(14))) {
                continue;
            }
            $msl = $station['unit'] === 'ม. รทก.';
            $rows[] = ['province_id' => $province->id, 'station_id' => $station['id'], 'name' => $station['name'],
                'datum' => $msl ? 'msl' : 'local', 'value' => $station['value'], 'bank' => $msl ? $station['bank'] : null,
                'measured_at' => Carbon::parse($station['measured_at'])->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s')];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('public_water_readings')->upsert($chunk, ['province_id', 'station_id', 'datum', 'measured_at'], ['name', 'value', 'bank']);
        }
    }
}
