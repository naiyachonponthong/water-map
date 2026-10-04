<?php

namespace App\Console\Commands;

use App\Models\Province;
use App\Support\PublicWaterService;
use Illuminate\Console\Command;
use Throwable;

class SyncPublicWater extends Command
{
    protected $signature = 'flood:sync-public-water';
    protected $description = 'Collect observed ThaiWater readings using one shared national cache';

    public function handle(PublicWaterService $water): int
    {
        $failures = 0;
        foreach (Province::where('is_active', true)->cursor() as $province) {
            try {
                $water->stations($province);
            } catch (Throwable $e) {
                $failures++;
                if ($failures === 1) {
                    report($e);
                }
            }
        }
        $this->info('Collected public station observations. Unavailable provinces: '.$failures);
        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
