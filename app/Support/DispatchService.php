<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\HelpRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * กติกาการสั่งการทีม: แนะนำทีม, เสนองาน, ตอบรับ/ปฏิเสธ, อัปเดตความคืบหน้า, ปิดงาน, หมดเวลาตอบรับ
 */
class DispatchService
{
    public function __construct(protected HelpRequestService $cases) {}

    /**
     * ทีมที่เหมาะกับเคสนี้ เรียงตาม: ยานพาหนะเหมาะ > ทีมว่าง > ถึงเร็ว
     *
     * @return Collection<int, array{team: Team, distance_m: ?int, eta: ?int, fit: bool, reasons: array, active: int}>
     */
    public function suggest(HelpRequest $case, int $limit = 5): Collection
    {
        $teams = Team::inProvince($case->province_id)
            ->where('is_active', true)
            ->whereIn('status', ['available', 'busy'])
            ->with('vehicles')
            ->withCount('activeAssignments')
            ->get();

        $needsBoat = $case->water_level >= 4;
        $medical = $case->hasVulnerable('bedridden', 'sick') || in_array('medicine', $case->needs ?? [], true);

        return $teams->map(function (Team $team) use ($case, $needsBoat, $medical) {
            $pos = $team->position();
            $distance = $pos ? (int) round(Geo::distance($pos[0], $pos[1], $case->lat, $case->lng)) : null;
            $speed = $team->speedKmh((int) $case->water_level);
            // เส้นทางจริงอ้อมกว่าเส้นตรงราว 1.3 เท่า
            $eta = $distance !== null ? (int) ceil(($distance * 1.3 / 1000) / $speed * 60) : null;

            $reasons = [];
            $fit = true;
            if ($needsBoat && ! $team->hasVehicle('boat', 'high_truck')) {
                $fit = false;
                $reasons[] = 'ไม่มีเรือ/รถยกสูง แต่น้ำ'.$case->waterLabel();
            }
            if ($medical && $team->hasVehicle('ambulance')) {
                $reasons[] = 'มีรถพยาบาล';
            }
            if ($team->status === 'busy') {
                $reasons[] = 'มีงานอยู่ '.$team->active_assignments_count.' งาน';
            }
            if (! $pos) {
                $reasons[] = 'ไม่ทราบตำแหน่งทีม';
            }

            return [
                'team' => $team,
                'distance_m' => $distance,
                'eta' => $eta,
                'fit' => $fit,
                'reasons' => $reasons,
                'active' => (int) $team->active_assignments_count,
            ];
        })
            ->sortBy(fn ($r) => [! $r['fit'], $r['team']->status !== 'available', $r['eta'] ?? PHP_INT_MAX])
            ->take($limit)
            ->values();
    }

    /** เสนองานให้ทีม (หรือบันทึกว่าทีมรับแล้วทันที เช่น ตกลงกันทางโทรศัพท์) */
    public function offer(HelpRequest $case, Team $team, ?User $user, string $via = 'dispatch', bool $accepted = false): Assignment
    {
        if (! $case->isOpen()) {
            throw new RuntimeException('เคสนี้ปิดแล้ว');
        }
        if ($team->province_id !== $case->province_id) {
            throw new RuntimeException('ทีมนี้อยู่คนละจังหวัด');
        }
        if ($case->assignment && $case->assignment->isActive()) {
            throw new RuntimeException('เคสนี้มีทีม '.$case->assignment->team->name.' อยู่แล้ว ยกเลิกงานเดิมก่อน');
        }

        return DB::transaction(function () use ($case, $team, $user, $via, $accepted) {
            $pos = $team->position();
            $distance = $pos ? (int) round(Geo::distance($pos[0], $pos[1], $case->lat, $case->lng)) : null;
            $eta = $distance !== null ? (int) ceil(($distance * 1.3 / 1000) / $team->speedKmh((int) $case->water_level) * 60) : null;

            $a = Assignment::create([
                'help_request_id' => $case->id,
                'team_id' => $team->id,
                'assigned_by' => $via === 'self' ? null : $user?->id,
                'responded_by' => $accepted ? $user?->id : null,
                'status' => $accepted ? 'accepted' : 'offered',
                'via' => $via,
                'distance_m' => $distance,
                'eta_minutes' => $eta,
                'offered_at' => now(),
                'responded_at' => $accepted ? now() : null,
            ]);

            $this->cases->changeStatus($case, $accepted ? 'accepted' : 'offered', $user,
                $accepted ? $this->teamLine($team, $eta, 'รับงานแล้ว') : 'เสนองานให้ทีม '.$team->name,
                ['team_id' => $team->id, 'assignment_id' => $a->id], $accepted);

            $this->syncTeamStatus($team);

            return $a;
        });
    }

    public function respond(Assignment $a, bool $accept, ?User $user, ?string $reason = null, ?string $note = null): void
    {
        if ($a->status !== 'offered') {
            throw new RuntimeException('งานนี้ตอบรับไปแล้ว หรือถูกยกเลิก');
        }
        $case = $a->helpRequest;

        DB::transaction(function () use ($a, $accept, $user, $reason, $note, $case) {
            $a->update([
                'status' => $accept ? 'accepted' : 'declined',
                'responded_by' => $user?->id,
                'responded_at' => now(),
                'decline_reason' => $accept ? null : ($reason ? (TeamOptions::DECLINE_REASONS[$reason] ?? $reason) : null),
                'note' => $note,
            ]);

            if ($accept) {
                $this->cases->changeStatus($case, 'accepted', $user, $this->teamLine($a->team, $a->eta_minutes, 'รับงานแล้ว'), [], true);
            } else {
                $this->cases->changeStatus($case, 'queued', $user,
                    'ทีม '.$a->team->name.' ปฏิเสธ'.($a->decline_reason ? ': '.$a->decline_reason : '').' กำลังหาทีมใหม่',
                    ['team_id' => null, 'assignment_id' => null]);
            }
            $this->syncTeamStatus($a->team);
        });
    }

    /** ทีมอัปเดตความคืบหน้า: en_route / on_site */
    public function progress(Assignment $a, string $step, ?User $user): void
    {
        $order = ['offered' => 0, 'accepted' => 1, 'en_route' => 2, 'on_site' => 3];
        if (! isset($order[$step]) || ! isset($order[$a->status]) || $order[$step] <= $order[$a->status]) {
            throw new RuntimeException('อัปเดตขั้นนี้ไม่ได้ สถานะงานตอนนี้: '.$a->statusLabel());
        }

        DB::transaction(function () use ($a, $step, $user) {
            $a->update([
                'status' => $step,
                'responded_at' => $a->responded_at ?? now(),
                'responded_by' => $a->responded_by ?? $user?->id,
                $step === 'en_route' ? 'en_route_at' : 'arrived_at' => now(),
            ]);
            $note = $step === 'en_route'
                ? $this->teamLine($a->team, $a->eta_minutes, 'กำลังเดินทาง')
                : 'ทีม '.$a->team->name.' ถึงที่เกิดเหตุแล้ว';
            $this->cases->changeStatus($a->helpRequest, $step, $user, $note, [], true);
        });
    }

    /** ปิดงาน: ช่วยแล้ว หรือปิดด้วยเหตุผลอื่น */
    public function complete(Assignment $a, ?User $user, string $outcome, ?int $rescued = null, ?string $note = null): void
    {
        if (! $a->isActive()) {
            throw new RuntimeException('งานนี้ปิดไปแล้ว');
        }
        $rescuedOutcomes = ['shelter', 'hospital', 'relatives', 'stay'];

        DB::transaction(function () use ($a, $user, $outcome, $rescued, $note, $rescuedOutcomes) {
            $a->update([
                'status' => 'done',
                'done_at' => now(),
                'arrived_at' => $a->arrived_at ?? now(),
                'people_rescued' => $rescued,
                'note' => $note ?: $a->note,
            ]);
            $to = in_array($outcome, $rescuedOutcomes, true) ? 'rescued' : 'closed';
            $this->cases->changeStatus($a->helpRequest, $to, $user, trim('ทีม '.$a->team->name.': '.(CaseOptions::OUTCOMES[$outcome] ?? $outcome).'. '.($note ?? '')), [
                'outcome' => $outcome,
                'people_rescued' => $rescued,
            ]);
            $this->syncTeamStatus($a->team);
        });
    }

    /** ศูนย์ยกเลิกงาน (เช่น จะย้ายไปให้ทีมที่ใกล้กว่า) เคสกลับเข้าคิว */
    public function cancel(Assignment $a, ?User $user, ?string $reason = null, string $as = 'cancelled'): void
    {
        if (! $a->isActive()) {
            return;
        }

        DB::transaction(function () use ($a, $user, $reason, $as) {
            $a->update(['status' => $as, 'note' => $reason ?: $a->note]);
            $case = $a->helpRequest;
            if ($case->assignment_id === $a->id && $case->isOpen()) {
                $label = $as === 'expired' ? 'ทีม '.$a->team->name.' ไม่ตอบรับในเวลาที่กำหนด' : 'ยกเลิกงานของทีม '.$a->team->name;
                $this->cases->changeStatus($case, 'queued', $user, trim($label.'. '.($reason ?? '')), ['team_id' => null, 'assignment_id' => null, '_actor' => 'system']);
            }
            $this->syncTeamStatus($a->team);
        });
    }

    /** เสนองานแล้วทีมไม่ตอบในเวลาที่กำหนด คืนเคสเข้าคิว */
    public function expireOffers(): int
    {
        $n = 0;
        Assignment::where('status', 'offered')->with('helpRequest', 'team')->get()
            ->each(function (Assignment $a) use (&$n) {
                $timeout = (int) Settings::get('offer_timeout_min', $a->helpRequest->province_id, 10);
                if ($a->offered_at && $a->offered_at->lt(now()->subMinutes($timeout))) {
                    $this->cancel($a, null, null, 'expired');
                    $n++;
                }
            });

        return $n;
    }

    /** ทีมว่าง/ติดภารกิจ ตามงานที่ยังทำอยู่ (ไม่แตะสถานะ พัก/ไม่ออกปฏิบัติ ที่ทีมตั้งเอง) */
    public function syncTeamStatus(Team $team): void
    {
        $team->refresh();
        if (! in_array($team->status, ['available', 'busy'], true)) {
            return;
        }
        $busy = $team->assignments()->whereIn('status', ['accepted', 'en_route', 'on_site'])->exists();
        $status = $busy ? 'busy' : 'available';
        if ($team->status !== $status) {
            $team->update(['status' => $status]);
        }
    }

    protected function teamLine(Team $team, ?int $eta, string $verb): string
    {
        return 'ทีม '.$team->name.' '.$verb.($eta ? ' คาดว่าถึงในราว '.$eta.' นาที' : '');
    }
}
