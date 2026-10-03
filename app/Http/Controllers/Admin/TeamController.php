<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\TeamOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $province = $this->province();
        $status = $request->query('status');

        $teams = Team::inProvince($province->id)
            ->with(['leader:id,name,phone', 'vehicles', 'district:id,name_th'])
            ->withCount(['members', 'activeAssignments'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("case status when 'available' then 0 when 'busy' then 1 when 'resting' then 2 else 3 end")
            ->orderBy('name')
            ->get();

        $counts = Team::inProvince($province->id)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $vehicleCounts = Vehicle::whereIn('team_id', Team::inProvince($province->id)->pluck('id'))
            ->where('status', '!=', 'maintenance')->selectRaw('type, count(*) c')->groupBy('type')->pluck('c', 'type');

        return view('admin.teams.index', [
            'province' => $province,
            'teams' => $teams,
            'counts' => $counts,
            'vehicleCounts' => $vehicleCounts,
            'districts' => District::where('province_id', $province->id)->orderBy('sort')->get(['id', 'name_th']),
            'leaders' => $this->assignableUsers($province->id),
        ]);
    }

    public function show(Team $team)
    {
        $this->authorizeProvince($team->province_id);
        $team->load(['members', 'vehicles', 'leader', 'district']);

        return view('admin.teams.show', [
            'team' => $team,
            'assignments' => $team->assignments()->with('helpRequest:id,code,requester_name,status,water_level,people_count')->latest('id')->limit(30)->get(),
            'candidates' => $this->assignableUsers($team->province_id, $team),
            'districts' => District::where('province_id', $team->province_id)->orderBy('sort')->get(['id', 'name_th']),
            'stats' => [
                'done' => $team->assignments()->where('status', 'done')->count(),
                'rescued' => (int) $team->assignments()->where('status', 'done')->sum('people_rescued'),
                'declined' => $team->assignments()->whereIn('status', ['declined', 'expired'])->count(),
                'avg_response' => $this->avgResponse($team),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $province = $this->province();
        $data = $this->validated($request, $province->id);
        $team = Team::create($data + ['province_id' => $province->id, 'status' => 'available']);
        $this->syncLeaderMembership($team);

        return redirect()->route('teams.show', $team)->with('success', "เพิ่มทีม {$team->name} แล้ว เพิ่มสมาชิกและยานพาหนะต่อได้เลย");
    }

    public function update(Request $request, Team $team)
    {
        $this->authorizeProvince($team->province_id);
        $team->update($this->validated($request, $team->province_id, $team));
        $this->syncLeaderMembership($team);

        return $this->ok("บันทึกทีม {$team->name} แล้ว");
    }

    public function destroy(Team $team)
    {
        $this->authorizeProvince($team->province_id);
        if ($team->activeAssignments()->exists()) {
            return back()->with('error', 'ทีมนี้ยังมีงานค้างอยู่ ปิดหรือยกเลิกงานก่อน');
        }
        $team->members()->detach();
        $team->delete();

        return $this->ok("ลบทีม {$team->name} แล้ว", 'teams.index');
    }

    /** เปลี่ยนสถานะทีม: ศูนย์ หรือสมาชิกทีมเอง */
    public function status(Request $request, Team $team)
    {
        $user = $request->user();
        $isMember = TeamMember::where('team_id', $team->id)->where('user_id', $user->id)->exists();
        abort_unless($isMember || ($user->can('teams.manage') && $user->canManageProvince($team->province_id)), 403);

        $status = $request->validate(['status' => ['required', Rule::in(array_keys(TeamOptions::STATUSES))]])['status'];
        if ($status === 'available' && $team->assignments()->whereIn('status', ['accepted', 'en_route', 'on_site'])->exists()) {
            $status = 'busy';
        }
        $team->update(['status' => $status]);

        return back()->with('success', "ทีม {$team->name}: ".TeamOptions::STATUSES[$status][0]);
    }

    public function addMember(Request $request, Team $team)
    {
        $this->authorizeProvince($team->province_id);
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role_in_team' => ['required', Rule::in(['leader', 'member'])],
        ], [], ['user_id' => 'สมาชิก']);

        $user = User::findOrFail($data['user_id']);
        abort_unless($user->province_id === $team->province_id, 422, 'ผู้ใช้นี้อยู่คนละจังหวัด');

        DB::transaction(function () use ($team, $user, $data) {
            TeamMember::where('user_id', $user->id)->delete(); // ย้ายทีม
            TeamMember::create(['team_id' => $team->id, 'user_id' => $user->id, 'role_in_team' => $data['role_in_team']]);
            if ($data['role_in_team'] === 'leader') {
                $this->setLeader($team, $user);
            } elseif (! $user->hasAnyRole(['team-leader', 'team-member', 'super-admin', 'province-admin', 'dispatcher'])) {
                $user->assignRole('team-member');
            }
        });

        return $this->ok("เพิ่ม {$user->name} เข้าทีมแล้ว");
    }

    public function removeMember(Team $team, User $user)
    {
        $this->authorizeProvince($team->province_id);
        TeamMember::where('team_id', $team->id)->where('user_id', $user->id)->delete();
        if ($team->leader_id === $user->id) {
            $team->update(['leader_id' => null]);
        }

        return $this->ok("นำ {$user->name} ออกจากทีมแล้ว");
    }

    public function makeLeader(Team $team, User $user)
    {
        $this->authorizeProvince($team->province_id);
        abort_unless(TeamMember::where('team_id', $team->id)->where('user_id', $user->id)->exists(), 422);
        $this->setLeader($team, $user);

        return $this->ok("ตั้ง {$user->name} เป็นหัวหน้าทีมแล้ว");
    }

    public function storeVehicle(Request $request, Team $team)
    {
        $this->authorizeProvince($team->province_id);
        $team->vehicles()->create($this->validatedVehicle($request));

        return $this->ok('เพิ่มยานพาหนะแล้ว');
    }

    public function updateVehicle(Request $request, Vehicle $vehicle)
    {
        $this->authorizeProvince($vehicle->team->province_id);
        $vehicle->update($this->validatedVehicle($request));

        return $this->ok('บันทึกยานพาหนะแล้ว');
    }

    public function destroyVehicle(Vehicle $vehicle)
    {
        $this->authorizeProvince($vehicle->team->province_id);
        $vehicle->delete();

        return $this->ok('ลบยานพาหนะแล้ว');
    }

    /* ------------------------------------------------------------------ */

    protected function validated(Request $request, int $provinceId, ?Team $team = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(TeamOptions::TYPES))],
            'phone' => ['nullable', 'string', 'max:20'],
            'leader_id' => ['nullable', Rule::exists('users', 'id')->where('province_id', $provinceId)],
            'district_id' => ['nullable', Rule::exists('districts', 'id')->where('province_id', $provinceId)],
            'base_address' => ['nullable', 'string', 'max:255'],
            'base_lat' => ['nullable', 'numeric', 'between:5,21'],
            'base_lng' => ['nullable', 'numeric', 'between:97,106'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['name' => 'ชื่อทีม', 'type' => 'ประเภท', 'leader_id' => 'หัวหน้าทีม']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['phone'] = $data['phone'] ? User::normalizePhone($data['phone']) : null;

        return $data;
    }

    protected function validatedVehicle(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(array_keys(TeamOptions::VEHICLES))],
            'name' => ['required', 'string', 'max:120'],
            'plate' => ['nullable', 'string', 'max:40'],
            'capacity' => ['nullable', 'integer', 'between:1,200'],
            'status' => ['required', Rule::in(array_keys(TeamOptions::VEHICLE_STATUSES))],
        ], [], ['type' => 'ประเภท', 'name' => 'ชื่อ', 'capacity' => 'จำนวนที่นั่ง']);
    }

    /** ผู้ใช้ในจังหวัดที่ยังไม่มีทีม (หรืออยู่ทีมนี้) */
    protected function assignableUsers(int $provinceId, ?Team $team = null)
    {
        return User::where('province_id', $provinceId)->where('status', 'active')
            ->where(fn ($q) => $q->whereDoesntHave('teamMembership')
                ->when($team, fn ($w) => $w->orWhereHas('teamMembership', fn ($m) => $m->where('team_id', '!=', $team->id))))
            ->orderBy('name')->get(['id', 'name', 'phone', 'organization']);
    }

    protected function setLeader(Team $team, User $user): void
    {
        TeamMember::where('team_id', $team->id)->where('role_in_team', 'leader')->where('user_id', '!=', $user->id)->update(['role_in_team' => 'member']);
        TeamMember::updateOrCreate(['user_id' => $user->id], ['team_id' => $team->id, 'role_in_team' => 'leader']);
        $team->update(['leader_id' => $user->id]);
        if (! $user->hasAnyRole(['team-leader', 'super-admin', 'province-admin', 'dispatcher'])) {
            $user->syncRoles(['team-leader']);
        }
    }

    protected function syncLeaderMembership(Team $team): void
    {
        if ($team->leader_id && ($leader = User::find($team->leader_id))) {
            $this->setLeader($team, $leader);
        }
    }

    protected function avgResponse(Team $team): ?int
    {
        $mins = $team->assignments()->whereNotNull('arrived_at')->get(['offered_at', 'arrived_at'])
            ->map(fn ($a) => $a->offered_at->diffInMinutes($a->arrived_at, true));

        return $mins->isEmpty() ? null : (int) round($mins->avg());
    }
}
