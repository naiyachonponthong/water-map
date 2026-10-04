<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Upgrade extends Command
{
    protected $signature = 'flood:upgrade';
    protected $description = 'Apply additive migrations and clear compiled configuration, routes and views';

    public function handle(): int
    {
        $this->warn('Back up your database, .env and storage before upgrading. This command never resets data.');
        $result = $this->call('migrate', ['--force' => true]);
        if ($result !== self::SUCCESS) {
            return $result;
        }
        foreach (['config:clear', 'route:clear', 'view:clear'] as $command) {
            $result = $this->call($command);
            if ($result !== self::SUCCESS) {
                return $result;
            }
        }
        $this->info('FloodThai upgraded. Existing APP_KEY, users, settings and uploads are preserved.');
        return self::SUCCESS;
    }
}
