<?php

namespace App\Support;

use App\Events\CaseChanged;
use App\Events\TeamChanged;
use App\Events\TeamSosRaised;
use App\Models\TeamSos;
use App\Models\HelpRequest;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ส่งเหตุการณ์ realtime แบบไม่ทำให้งานหลักพัง: Reverb ล่มหรือยังไม่เปิด งานบันทึกเคสยังสำเร็จตามปกติ
 * หน้าจอที่ไม่ได้รับ socket จะดึงข้อมูลใหม่เองทุก 20 วินาที
 */
class Live
{
    public static function enabled(): bool
    {
        return config('broadcasting.default') === 'reverb' && filled(config('broadcasting.connections.reverb.key'));
    }

    /**
     * บอกว่าข้อมูลของจังหวัดเปลี่ยน ให้ snapshot ที่แคชไว้หมดอายุทันที
     * (ทำเสมอแม้ไม่ได้เปิด Reverb เพราะหน้าจอที่ดึงข้อมูลเองก็ต้องเห็นค่าใหม่)
     */
    public static function bump(?int $provinceId): void
    {
        if (! $provinceId) {
            return;
        }
        try {
            $key = 'live-ver:'.$provinceId;
            \Illuminate\Support\Facades\Cache::forever($key, (int) \Illuminate\Support\Facades\Cache::get($key, 0) + 1);
        } catch (\Throwable) {
            // แคชล่มก็ยังทำงานต่อได้ แค่ไม่ได้แคช
        }
    }

    public static function version(int $provinceId): int
    {
        return (int) \Illuminate\Support\Facades\Cache::get('live-ver:'.$provinceId, 0);
    }

    public static function caseChanged(HelpRequest $case, string $type, ?string $summary = null): void
    {
        static::bump($case->province_id);
        static::send(fn () => broadcast(new CaseChanged($case, $type, $summary)));
    }

    public static function teamChanged(Team $team): void
    {
        static::bump($team->province_id);
        static::send(fn () => broadcast(new TeamChanged($team)));
    }

    public static function signal(int $provinceId, string $name, array $data = []): void
    {
        static::bump($provinceId);
        static::send(fn () => broadcast(new \App\Events\ProvinceSignal($provinceId, $name, $data)));
    }

    public static function sos(TeamSos $sos): void
    {
        static::bump($sos->province_id);
        static::send(fn () => broadcast(new TeamSosRaised($sos)));
    }

    /** ค่าที่หน้าเว็บใช้ต่อ Reverb (เฉพาะค่าสาธารณะ ไม่มี secret) */
    public static function clientConfig(): ?array
    {
        if (! static::enabled()) {
            return null;
        }
        $o = config('broadcasting.connections.reverb.options');

        return [
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => $o['host'] ?: request()->getHost(),
            'port' => (int) $o['port'],
            'tls' => (bool) $o['useTLS'],
        ];
    }

    protected static function send(callable $fn): void
    {
        if (! static::enabled()) {
            return;
        }
        // ส่งหลัง commit เท่านั้น หน้าจอที่โหลดข้อมูลใหม่จะได้เห็นค่าล่าสุด (ไม่มี transaction = ส่งทันที)
        DB::afterCommit(function () use ($fn) {
            try {
                $fn();
            } catch (\Throwable $e) {
                Log::warning('ส่ง realtime ไม่สำเร็จ: '.$e->getMessage());
            }
        });
    }
}
