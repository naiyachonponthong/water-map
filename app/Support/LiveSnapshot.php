<?php

namespace App\Support;

use App\Models\HelpRequest;
use App\Models\HelpRequestEvent;
use App\Models\Province;
use App\Models\Team;
use App\Models\TeamSos;

/**
 * ภาพรวมสดของจังหวัด ใช้ทั้งแดชบอร์ดศูนย์สั่งการและโหมดทีวี
 */
class LiveSnapshot
{
    public static function stats(int $pid): array
    {
        $cases = HelpRequest::inProvince($pid);
        $teams = Team::inProvince($pid)->where('is_active', true);
        $rescuedToday = (clone $cases)->where('status', 'rescued')->where('closed_at', '>=', today());

        return [
            'triage' => (clone $cases)->whereIn('status', ['new', 'screening'])->count(),
            'queued' => (clone $cases)->whereIn('status', ['queued', 'offered'])->count(),
            'active' => (clone $cases)->whereIn('status', ['accepted', 'en_route', 'on_site'])->count(),
            'rescued_today' => (clone $rescuedToday)->count(),
            'people_today' => (int) (clone $rescuedToday)->sum('people_rescued'),
            'critical_open' => (clone $cases)->open()->where('priority', 'critical')->count(),
            'teams_available' => (clone $teams)->where('status', 'available')->count(),
            'teams_busy' => (clone $teams)->where('status', 'busy')->count(),
            'longest_wait' => static::longestWait($pid),
            'risks_threatened' => \App\Models\RiskPoint::inProvince($pid)->live()->where('status', 'threatened')->count(),
            'proactive_open' => (clone $cases)->open()->where('source', 'proactive')->count(),
            'reports_live' => \App\Models\WaterReport::inProvince($pid)->current()->count(),
            'reports_review' => \App\Models\WaterReport::inProvince($pid)->where('status', 'pending')->count(),
            'evacuees_in' => \App\Models\Evacuee::inProvince($pid)->where('status', 'in')->count(),
            'shelters_open' => \App\Models\Shelter::inProvince($pid)->whereIn('status', ['open', 'full'])->count(),
            'alerts_active' => \App\Models\Alert::inProvince($pid)->active()->count(),
            'stations_alarm' => \App\Models\WaterStation::inProvince($pid)->where('is_active', true)->whereIn('status', ['warning', 'critical'])->count(),
        ];
    }

    /** เคสที่รอทีมนานที่สุด (นาที) */
    public static function longestWait(int $pid): ?int
    {
        $oldest = HelpRequest::inProvince($pid)->whereIn('status', CaseOptions::WAITING)->oldest()->value('created_at');

        return $oldest ? (int) \Illuminate\Support\Carbon::parse($oldest)->diffInMinutes(now(), true) : null;
    }

    public static function urgent(int $pid, int $limit = 8)
    {
        return HelpRequest::inProvince($pid)->whereIn('status', CaseOptions::WAITING)->urgentFirst()->limit($limit)
            ->with('subdistrict:id,name_th,code', 'district:id,name_th')->get();
    }

    public static function feed(int $pid, int $limit = 15)
    {
        return HelpRequestEvent::query()
            ->whereHas('helpRequest', fn ($q) => $q->where('province_id', $pid))
            ->with(['helpRequest:id,code,requester_name,priority,status', 'user:id,name'])
            ->whereIn('type', ['created', 'status', 'update', 'merged'])
            ->latest('id')->limit($limit)->get();
    }

    public static function caseFeatures(int $pid): array
    {
        return HelpRequest::inProvince($pid)->open()
            ->get(['id', 'code', 'lat', 'lng', 'status', 'priority', 'priority_score', 'water_level', 'people_count', 'requester_name', 'created_at', 'team_id'])
            ->map(fn (HelpRequest $c) => [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [$c->lng, $c->lat]],
                'properties' => [
                    'id' => $c->id,
                    'code' => $c->code,
                    'status' => $c->statusLabel(),
                    'priority' => $c->priority,
                    'priority_label' => $c->priorityLabel(),
                    'color' => $c->priorityColor(),
                    'score' => $c->priority_score,
                    'water' => $c->waterLabel(),
                    'people' => $c->people_count,
                    'name' => $c->requester_name,
                    'ago' => $c->createdLabel(),
                    'has_team' => (bool) $c->team_id,
                    'url' => route('cases.show', $c),
                ],
            ])->all();
    }

    public static function teamFeatures(int $pid): array
    {
        return Team::inProvince($pid)->where('is_active', true)
            ->with('activeAssignments.helpRequest:id,code')
            ->get()
            ->map(function (Team $t) {
                $pos = $t->position();
                if (! $pos) {
                    return null;
                }

                return [
                    'type' => 'Feature',
                    'geometry' => ['type' => 'Point', 'coordinates' => [$pos[1], $pos[0]]],
                    'properties' => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'status' => $t->status,
                        'status_label' => $t->statusLabel(),
                        'color' => $t->statusColor(),
                        'jobs' => $t->activeAssignments->map(fn ($a) => $a->helpRequest?->code)->filter()->values(),
                        'live' => (bool) ($t->last_seen_at && $t->last_seen_at->gt(now()->subMinutes(10))),
                        'seen' => $t->last_seen_at ? ThaiDate::ago($t->last_seen_at) : null,
                    ],
                ];
            })->filter()->values()->all();
    }

    /** SOS ที่ยังไม่ปิดของจังหวัด */
    /** จุดเสี่ยงที่ใช้งานอยู่ สำหรับชั้นแผนที่ศูนย์สั่งการ */
    public static function riskFeatures(int $pid): array
    {
        return \App\Models\RiskPoint::inProvince($pid)->live()->get()
            ->map(fn (\App\Models\RiskPoint $r) => [
                'type' => 'Feature',
                'geometry' => $r->zone ? $r->zoneArray() : ['type' => 'Point', 'coordinates' => [$r->lng, $r->lat]],
                'properties' => [
                    'id' => $r->id, 'name' => $r->name, 'type' => $r->typeLabel(), 'icon' => $r->icon(), 'color' => $r->color(),
                    'status' => $r->status, 'radius' => $r->zone ? null : $r->radius(), 'lat' => $r->lat, 'lng' => $r->lng,
                    'reason' => $r->threat_reason, 'description' => $r->description,
                ],
            ])->values()->all();
    }

    /** รายงานระดับน้ำที่แสดงอยู่ (ชั้นแผนที่) */
    public static function reportFeatures(int $pid): array
    {
        return \App\Models\WaterReport::inProvince($pid)->current()->latest('updated_at')->limit(800)->get()
            ->map(fn (\App\Models\WaterReport $r) => [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [$r->lng, $r->lat]],
                'properties' => [
                    'id' => $r->id, 'level' => $r->level, 'label' => $r->levelLabel(), 'color' => $r->color(),
                    'opacity' => $r->freshness(), 'trusted' => $r->isTrusted(), 'trend' => $r->trendLabel(),
                    'source' => $r->sourceLabel(), 'ago' => ThaiDate::ago($r->updated_at), 'note' => $r->note,
                ],
            ])->values()->all();
    }

    public static function alerts(int $pid)
    {
        return \App\Models\Alert::inProvince($pid)->active()->severeFirst()->limit(4)->get();
    }

    public static function sos(int $pid)
    {
        return TeamSos::where('province_id', $pid)->whereIn('status', ['open', 'ack'])
            ->with('team:id,name,phone,leader_id', 'team.leader:id,name,phone', 'user:id,name,phone', 'helpRequest:id,code', 'acker:id,name')
            ->latest()->get();
    }

    /**
     * snapshot ที่แคชไว้ร่วมกันทุกจอของจังหวัด (หมดอายุทันทีเมื่อข้อมูลเปลี่ยนผ่าน Live::bump หรือ 15 วินาที)
     * จอหลายสิบเครื่องที่ดึงพร้อมกันหลัง event เดียว จะคำนวณจริงแค่ครั้งเดียว
     */
    public static function cached(Province $province, int $sinceEvent = 0, bool $tv = false): array
    {
        $user = auth()->user();
        // HTML บางส่วนมีปุ่มตามสิทธิ์ แยกแคชตามชุดสิทธิ์
        $perm = $user ? implode('', array_map(fn ($p) => (int) $user->can($p), ['dispatch.manage', 'cases.manage', 'announcements.manage'])) : '000';
        $key = 'live-snap:'.$province->id.':'.Live::version($province->id).':'.$perm.($tv ? ':tv' : '');

        $snap = \Illuminate\Support\Facades\Cache::remember($key, 15, fn () => static::build($province, 0, $tv));
        $snap['new_critical'] = $sinceEvent > 0 ? static::newCritical($province->id, $sinceEvent) : collect();
        $snap['last_event_id'] = max((int) $snap['last_event_id'], $sinceEvent);

        return $snap;
    }

    /** เคสวิกฤตที่เข้ามาหลัง event ที่จอเห็นล่าสุด (ใช้ส่งเสียงเตือนตอนไม่มี realtime) */
    public static function newCritical(int $pid, int $sinceEvent)
    {
        return HelpRequestEvent::query()->where('id', '>', $sinceEvent)->whereIn('type', ['created', 'update'])
            ->whereHas('helpRequest', fn ($q) => $q->where('province_id', $pid)->where('priority', 'critical'))
            ->with('helpRequest:id,code')->limit(20)->get()
            ->map(fn ($e) => $e->helpRequest->code)->unique()->values();
    }

    public static function build(Province $province, int $sinceEvent = 0, ?bool $tv = null): array
    {
        $pid = $province->id;
        $feed = static::feed($pid);

        // เคสวิกฤตที่เพิ่งเข้ามาหลังรอบก่อน (ใช้ส่งเสียงเตือนตอนไม่มี realtime)
        $newCritical = $sinceEvent > 0
            ? $feed->filter(fn ($e) => $e->id > $sinceEvent && $e->helpRequest?->priority === 'critical' && in_array($e->type, ['created', 'update'], true))
                ->map(fn ($e) => $e->helpRequest->code)->unique()->values()
            : collect();

        $sos = static::sos($pid);

        return [
            'stats' => static::stats($pid),
            'sos_open' => $sos->where('status', 'open')->pluck('id')->values(),
            'sos_html' => view('live._sos', ['sos' => $sos])->render(),
            'cases' => static::caseFeatures($pid),
            'teams' => static::teamFeatures($pid),
            'risks' => static::riskFeatures($pid),
            'reports' => static::reportFeatures($pid),
            'stations' => \App\Http\Controllers\Admin\StationController::features($pid, false),
            'alerts_html' => view('live._alerts', ['alerts' => static::alerts($pid), 'tv' => $tv ?? request()->routeIs('live.tv')])->render(),
            'alerts_unack' => \App\Models\Alert::inProvince($pid)->active()->whereNull('acknowledged_at')->pluck('id')->values(),
            'urgent_html' => view('live._urgent', ['urgent' => static::urgent($pid)])->render(),
            'feed_html' => view('live._feed', ['feed' => $feed])->render(),
            'last_event_id' => (int) ($feed->first()->id ?? $sinceEvent),
            'new_critical' => $newCritical,
            'time' => now()->format('H:i'),
        ];
    }
}
