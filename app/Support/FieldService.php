<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\FieldAction;
use App\Models\HelpRequest;
use App\Models\RiskPoint;
use App\Models\Team;
use App\Models\TeamSos;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * แอปภาคสนาม: สถานะของทีม + การกระทำแบบส่งซ้ำได้ (idempotent) สำหรับคิวที่ค้างตอนออฟไลน์
 */
class FieldService
{
    public const TYPES = ['respond', 'progress', 'complete', 'pick', 'team_status', 'note', 'water', 'sos', 'sos_safe', 'household_check', 'report'];

    public function __construct(protected DispatchService $dispatch, protected HelpRequestService $cases, protected RiskEngine $risk) {}

    /* ---------------- สถานะทั้งหมดที่แอปต้องใช้ ---------------- */

    public function state(Team $team): array
    {
        $team->load('vehicles');
        $pos = $team->position();
        $selfAssign = (bool) Settings::get('team_self_assign', $team->province_id);

        $jobs = $team->assignments()->whereIn('status', TeamOptions::ACTIVE)
            ->with('helpRequest.subdistrict', 'helpRequest.district', 'helpRequest.household')->orderBy('offered_at')->get()
            ->map(fn (Assignment $a) => [
                'assignment_id' => $a->id,
                'status' => $a->status,
                'status_label' => $a->statusLabel(),
                'eta' => $a->eta_minutes,
                'distance_m' => $a->distance_m,
                'via' => $a->via,
                'case' => $this->caseData($a->helpRequest, $pos, true),
            ])->values();

        $nearby = collect();
        if ($selfAssign) {
            $nearby = HelpRequest::inProvince($team->province_id)->where('status', 'queued')
                ->with('subdistrict', 'district')->urgentFirst()->limit(40)->get()
                ->map(fn ($c) => $this->caseData($c, $pos, false))
                ->sortBy(fn ($c) => [-$c['score'], $c['distance_m'] ?? PHP_INT_MAX])
                ->take(15)->values();
        }

        $sos = TeamSos::where('team_id', $team->id)->whereIn('status', ['open', 'ack'])->latest()->first();
        $done = $team->assignments()->where('status', 'done')->where('done_at', '>=', today());

        return [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'status' => $team->status,
                'status_label' => $team->statusLabel(),
                'color' => $team->statusColor(),
                'position' => $pos,
                'last_seen' => $team->last_seen_at?->toIso8601String(),
                'vehicles' => $team->vehicles->map(fn ($v) => ['type' => $v->type, 'label' => $v->typeLabel(), 'name' => $v->name, 'capacity' => $v->capacity, 'status' => $v->status])->values(),
                'self_assign' => $selfAssign,
            ],
            'jobs' => $jobs,
            'nearby' => $nearby,
            'sos' => $sos ? ['id' => $sos->id, 'status' => $sos->status, 'status_label' => $sos->statusLabel(), 'kind' => $sos->kindLabel(), 'at' => $sos->created_at->toIso8601String()] : null,
            'risks' => $this->risks($team, $pos),
            'reports' => $this->reports($team, $pos),
            'alerts' => \App\Models\Alert::inProvince($team->province_id)->active()->severeFirst()->limit(3)->get()
                ->map(fn ($a) => ['id' => $a->id, 'title' => $a->title, 'body' => $a->body, 'level' => $a->levelLabel(), 'color' => $a->color()])->values(),
            'today' => ['done' => (clone $done)->count(), 'people' => (int) (clone $done)->sum('people_rescued')],
            'hotline' => Settings::get('hotline', $team->province_id),
            'server_time' => now()->toIso8601String(),
        ];
    }

    /** ข้อมูลเคสสำหรับแอป: งานของทีมเห็นเบอร์เต็ม เคสในคิวยังไม่เห็นเบอร์ */
    public function caseData(HelpRequest $c, ?array $pos, bool $withPhone): array
    {
        $lv = config('floodthai.water_levels.'.$c->water_level);

        return [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->requester_name,
            'phone' => $withPhone ? $c->requester_phone : null,
            'contact_name' => $withPhone ? $c->contact_name : null,
            'contact_phone' => $withPhone ? $c->contact_phone : null,
            'lat' => $c->lat,
            'lng' => $c->lng,
            'water_level' => (int) $c->water_level,
            'water' => $lv['label'] ?? '',
            'water_color' => $lv['color'] ?? '#999',
            'people' => $c->people_count,
            'floor' => $c->floor_level,
            'vulnerable' => $c->vulnerableLabels(),
            'critical_people' => $c->hasVulnerable('bedridden', 'sick', 'disabled', 'infant'),
            'needs' => $c->needLabels(),
            'note' => $c->needs_note,
            'address' => $c->address_text,
            'landmark' => $c->landmark,
            'area' => $c->areaLabel(),
            'priority' => $c->priority,
            'priority_label' => $c->priorityLabel(),
            'color' => $c->priorityColor(),
            'score' => $c->priority_score,
            'waiting' => $c->waitingLabel(),
            'distance_m' => $pos ? (int) Geo::distance($pos[0], $pos[1], $c->lat, $c->lng) : null,
            'proactive' => $c->source === 'proactive',
            'household' => $withPhone && $c->household ? $this->householdData($c->household) : null,
        ];
    }

    /** ข้อมูลครัวเรือนเปราะบาง ให้เฉพาะทีมที่ได้งานเคสนั้น */
    protected function householdData(\App\Models\VulnerableHousehold $h): array
    {
        return [
            'id' => $h->id,
            'name' => $h->head_name,
            'members' => $h->members,
            'conditions' => $h->conditionLabels(),
            'note' => $h->note,
            'caretaker_name' => $h->caretaker_name,
            'caretaker_phone' => $h->caretaker_phone,
            'last_check' => $h->last_check_status ? (RiskOptions::CHECK[$h->last_check_status][0] ?? null) : null,
            'last_checked_at' => $h->last_checked_at?->toIso8601String(),
        ];
    }

    /** รายงานระดับน้ำล่าสุดรอบทีม (10 กม.) */
    protected function reports(Team $team, ?array $pos): array
    {
        return \App\Models\WaterReport::inProvince($team->province_id)->current()->latest('updated_at')->limit(400)->get()
            ->map(fn ($r) => [
                'id' => $r->id, 'lat' => $r->lat, 'lng' => $r->lng, 'level' => $r->level, 'label' => $r->levelLabel(), 'color' => $r->color(),
                'trusted' => $r->isTrusted(), 'trend' => $r->trendLabel(), 'note' => $r->note, 'ago' => ThaiDate::ago($r->updated_at),
                'distance_m' => $pos ? (int) Geo::distance($pos[0], $pos[1], $r->lat, $r->lng) : null,
            ])
            ->filter(fn ($r) => $r['distance_m'] === null || $r['distance_m'] <= 10000)
            ->take(80)->values()->all();
    }

    /** จุดเสี่ยงที่ทีมควรรู้: กำลังเตือนมาก่อน แล้วเรียงตามระยะ */
    protected function risks(Team $team, ?array $pos): array
    {
        return RiskPoint::inProvince($team->province_id)->live()->get()
            ->map(fn (RiskPoint $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'type' => $r->typeLabel(),
                'icon' => $r->icon(),
                'color' => $r->color(),
                'status' => $r->status,
                'severity' => $r->severity,
                'lat' => $r->lat,
                'lng' => $r->lng,
                'radius' => $r->zone ? null : $r->radius(),
                'zone' => $r->zoneArray(),
                'reason' => $r->threat_reason,
                'description' => $r->description,
                'distance_m' => $pos ? (int) Geo::distance($pos[0], $pos[1], $r->lat, $r->lng) : null,
            ])
            ->sortBy(fn ($r) => [$r['status'] === 'threatened' ? 0 : 1, $r['distance_m'] ?? PHP_INT_MAX])
            ->take(40)->values()->all();
    }

    /* ---------------- การกระทำ ---------------- */

    /**
     * ทำงานครั้งเดียวต่อ key: ส่งซ้ำ (เน็ตหลุดแล้วส่งใหม่) ได้ผลเดิมกลับไป
     *
     * @return array{ok: bool, message: string, duplicate?: bool}
     */
    public function apply(User $user, Team $team, string $key, string $type, array $payload, ?string $clientAt = null): array
    {
        if ($done = FieldAction::where('key', $key)->first()) {
            abort_if($done->user_id !== $user->id, 409, 'key ซ้ำกับผู้ใช้อื่น');

            return ($done->result ?? ['ok' => $done->ok, 'message' => '']) + ['duplicate' => true];
        }

        try {
            $message = DB::transaction(fn () => $this->run($user, $team, $type, $payload));
            $result = ['ok' => true, 'message' => $message];
        } catch (RuntimeException $e) {
            $result = ['ok' => false, 'message' => $e->getMessage()];
        }

        FieldAction::create([
            'key' => $key,
            'user_id' => $user->id,
            'type' => $type,
            'payload' => $payload,
            'result' => $result,
            'ok' => $result['ok'],
            'client_at' => $clientAt ? \Illuminate\Support\Carbon::parse($clientAt) : null,
        ]);

        return $result;
    }

    protected function run(User $user, Team $team, string $type, array $p): string
    {
        switch ($type) {
            case 'respond':
                $a = $this->assignment($team, $p);
                $this->dispatch->respond($a, (bool) ($p['accept'] ?? false), $user, $p['reason'] ?? null, $p['note'] ?? null);

                return ($p['accept'] ?? false) ? 'รับงาน '.$a->helpRequest->code.' แล้ว' : 'ปฏิเสธงานแล้ว';

            case 'progress':
                $a = $this->assignment($team, $p);
                $step = in_array($p['step'] ?? '', ['en_route', 'on_site'], true) ? $p['step'] : throw new RuntimeException('ขั้นตอนไม่ถูกต้อง');
                $this->dispatch->progress($a, $step, $user);

                return TeamOptions::ASSIGNMENT[$step][0];

            case 'complete':
                $a = $this->assignment($team, $p);
                $outcome = $p['outcome'] ?? '';
                if (! array_key_exists($outcome, CaseOptions::OUTCOMES) || in_array($outcome, ['duplicate', 'self_safe'], true)) {
                    throw new RuntimeException('เลือกผลการช่วยเหลือ');
                }
                $rescued = isset($p['people_rescued']) && $p['people_rescued'] !== '' ? max(0, min(500, (int) $p['people_rescued'])) : null;
                $this->dispatch->complete($a, $user, $outcome, $rescued, isset($p['note']) ? mb_substr((string) $p['note'], 0, 500) : null);

                return 'ปิดงาน '.$a->helpRequest->code.' แล้ว';

            case 'pick':
                if (! Settings::get('team_self_assign', $team->province_id)) {
                    throw new RuntimeException('ศูนย์ยังไม่เปิดให้ทีมหยิบเคสเอง');
                }
                $case = HelpRequest::inProvince($team->province_id)->lockForUpdate()->find($p['case_id'] ?? 0);
                if (! $case || $case->status !== 'queued') {
                    throw new RuntimeException(($case?->code ?? 'เคสนี้').' มีทีมอื่นรับไปแล้ว');
                }
                $this->dispatch->offer($case, $team, $user, 'self', true);

                return 'รับ '.$case->code.' แล้ว';

            case 'team_status':
                $status = $p['status'] ?? '';
                if (! array_key_exists($status, TeamOptions::STATUSES)) {
                    throw new RuntimeException('สถานะไม่ถูกต้อง');
                }
                if ($status === 'available' && $team->assignments()->whereIn('status', ['accepted', 'en_route', 'on_site'])->exists()) {
                    $status = 'busy';
                }
                $team->update(['status' => $status]);

                return 'สถานะทีม: '.TeamOptions::STATUSES[$status][0];

            case 'note':
                $case = $this->teamCase($team, $p);
                $note = trim(mb_substr((string) ($p['note'] ?? ''), 0, 1000));
                if ($note === '') {
                    throw new RuntimeException('พิมพ์ข้อความก่อน');
                }
                $this->cases->event($case, 'note', ['user' => $user, 'note' => 'ทีม '.$team->name.': '.$note]);

                return 'ส่งบันทึกให้ศูนย์แล้ว';

            case 'water':
                $case = $this->teamCase($team, $p);
                $level = (int) ($p['water_level'] ?? 0);
                if ($level < 1 || $level > 6) {
                    throw new RuntimeException('ระดับน้ำไม่ถูกต้อง');
                }
                $from = $case->water_level;
                $case->water_level = $level;
                $this->cases->applyPriority($case);
                $case->save();
                $this->cases->event($case, 'update', [
                    'user' => $user,
                    'note' => 'ทีมยืนยันระดับน้ำหน้างาน: '.CaseOptions::waterLevel($level),
                    'data' => ['water_from' => $from, 'water_to' => $level, 'verified_by_team' => $team->id],
                ]);

                return 'บันทึกระดับน้ำแล้ว';

            case 'sos':
                if (TeamSos::where('team_id', $team->id)->whereIn('status', ['open', 'ack'])->exists()) {
                    throw new RuntimeException('ทีมมี SOS ที่ยังเปิดอยู่ ศูนย์กำลังประสาน');
                }
                $kind = array_key_exists($p['kind'] ?? '', TeamSos::KINDS) ? $p['kind'] : 'backup';
                $active = $team->assignments()->whereIn('status', ['accepted', 'en_route', 'on_site'])->latest('id')->first();
                $pos = isset($p['lat'], $p['lng']) && is_numeric($p['lat']) && is_numeric($p['lng']) ? [(float) $p['lat'], (float) $p['lng']] : $team->position();
                $sos = TeamSos::create([
                    'province_id' => $team->province_id,
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                    'help_request_id' => $active?->help_request_id,
                    'kind' => $kind,
                    'lat' => $pos[0] ?? null,
                    'lng' => $pos[1] ?? null,
                    'note' => isset($p['note']) ? mb_substr((string) $p['note'], 0, 500) : null,
                ]);
                Live::sos($sos);

                return 'ส่ง SOS ถึงศูนย์แล้ว';

            case 'sos_safe':
                $sos = TeamSos::where('team_id', $team->id)->whereIn('status', ['open', 'ack'])->latest()->first();
                if ($sos) {
                    $sos->update(['status' => 'resolved', 'resolved_by' => $user->id, 'resolved_at' => now(), 'resolve_note' => 'ทีมแจ้งว่าปลอดภัยแล้ว']);
                    Live::sos($sos);
                }

                return 'แจ้งศูนย์ว่าปลอดภัยแล้ว';

            case 'report':
                $lat = (float) ($p['lat'] ?? 0);
                $lng = (float) ($p['lng'] ?? 0);
                $level = (int) ($p['level'] ?? 0);
                if ($lat < 5 || $lat > 21 || $lng < 97 || $lng > 106) {
                    throw new RuntimeException('ไม่มีพิกัด เปิด GPS แล้วลองใหม่');
                }
                if ($level < 1 || $level > 6) {
                    throw new RuntimeException('เลือกระดับน้ำ');
                }
                app(WaterReportService::class)->submit($team->province, [
                    'lat' => $lat, 'lng' => $lng, 'level' => $level,
                    'trend' => in_array($p['trend'] ?? null, array_keys(ReportOptions::TRENDS), true) ? $p['trend'] : null,
                    'note' => isset($p['note']) ? mb_substr((string) $p['note'], 0, 500) : null,
                    'accuracy_m' => isset($p['accuracy_m']) ? (int) $p['accuracy_m'] : null,
                ], ['source' => 'team', 'user' => $user, 'team_id' => $team->id]);

                return 'รายงานระดับน้ำ'.CaseOptions::waterLevel($level).' แล้ว';

            case 'household_check':
                $case = $this->teamCase($team, $p);
                $h = $case->household;
                if (! $h) {
                    throw new RuntimeException('เคสนี้ไม่ได้ผูกกับครัวเรือนเปราะบาง');
                }
                $status = $p['status'] ?? '';
                if (! array_key_exists($status, RiskOptions::CHECK)) {
                    throw new RuntimeException('เลือกผลการเยี่ยม');
                }
                $note = isset($p['note']) ? mb_substr(trim((string) $p['note']), 0, 500) : null;
                $this->risk->recordCheck($h, $status, $user, $team->id, $note ?: null);
                $this->cases->event($case, 'note', ['user' => $user, 'note' => 'ทีม '.$team->name.' เยี่ยมบ้าน: '.RiskOptions::CHECK[$status][0].($note ? '. '.$note : '')]);

                return 'บันทึกผลเยี่ยม: '.RiskOptions::CHECK[$status][0];
        }

        throw new RuntimeException('ไม่รู้จักคำสั่ง '.$type);
    }

    protected function assignment(Team $team, array $p): Assignment
    {
        $a = Assignment::with('helpRequest', 'team')->find($p['assignment_id'] ?? 0);
        if (! $a || $a->team_id !== $team->id) {
            throw new RuntimeException('งานนี้ไม่ใช่ของทีมคุณ หรือศูนย์ย้ายงานไปแล้ว');
        }

        return $a;
    }

    protected function teamCase(Team $team, array $p): HelpRequest
    {
        $case = HelpRequest::find($p['case_id'] ?? 0);
        if (! $case || $case->team_id !== $team->id) {
            throw new RuntimeException('เคสนี้ไม่ได้อยู่กับทีมคุณแล้ว');
        }

        return $case;
    }
}
