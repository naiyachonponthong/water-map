<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evacuee;
use App\Models\HelpRequest;
use App\Models\Shelter;
use App\Models\ShelterNeed;
use App\Models\Subdistrict;
use App\Models\User;
use App\Support\ReliefOptions;
use App\Support\ShelterService;
use App\Support\SupplyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * ศูนย์พักพิง + ลงทะเบียนผู้อพยพ + ของที่ศูนย์ต้องการ
 */
class ShelterController extends Controller
{
    public function index(Request $request)
    {
        $province = $this->province();
        $shelters = Shelter::forUser($request->user(), $province->id)
            ->with('district:id,name_th')->withCount(['needs as open_needs' => fn ($q) => $q->where('status', 'open')])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByRaw("case status when 'open' then 0 when 'full' then 1 when 'preparing' then 2 else 3 end")
            ->orderBy('name')->get();

        $all = Shelter::inProvince($province->id)->whereIn('status', ['open', 'full']);

        return view('admin.shelters.index', [
            'province' => $province,
            'shelters' => $shelters,
            'stats' => [
                'open' => (clone $all)->count(),
                'capacity' => (int) (clone $all)->sum('capacity'),
                'people' => Evacuee::inProvince($province->id)->where('status', 'in')->count(),
                'today' => Evacuee::inProvince($province->id)->where('checked_in_at', '>=', today())->count(),
            ],
            'staffUsers' => User::where('province_id', $province->id)->where('status', 'active')->role('shelter-staff')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, Shelter $shelter, SupplyService $supply)
    {
        $this->authorizeShelter($request, $shelter);
        $evacuees = $shelter->evacuees()
            ->when($request->query('show') !== 'all', fn ($q) => $q->where('status', 'in'))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->query('q').'%')->orWhere('code', $request->query('q'))
                ->orWhere('phone_hash', \App\Support\HelpRequestService::phoneHash((string) $request->query('q')))))
            ->orderByRaw('coalesce(family_code, code)')->orderBy('id')
            ->paginate(50)->withQueryString();

        $in = $shelter->evacuees()->where('status', 'in');
        $table = $supply->table($shelter->province);

        return view('admin.shelters.show', [
            'shelter' => $shelter->load('district', 'subdistrict', 'staff:id,name,phone'),
            'evacuees' => $evacuees,
            'summary' => [
                'age' => (clone $in)->selectRaw('age_group, count(*) n')->groupBy('age_group')->pluck('n', 'age_group'),
                'needs' => (clone $in)->whereNotNull('needs')->pluck('needs')->flatten()->countBy(),
                'families' => (clone $in)->whereNotNull('family_code')->distinct()->count('family_code'),
            ],
            'needs' => $shelter->needs()->latest()->get(),
            'stock' => \App\Models\SupplyItem::inProvince($shelter->province_id)->orderBy('category')->orderBy('name')->get()
                ->map(fn ($i) => ['item' => $i, 'qty' => $table[$i->id][$shelter->id] ?? 0])->filter(fn ($r) => $r['qty'] !== 0)->values(),
            'others' => Shelter::inProvince($shelter->province_id)->where('id', '!=', $shelter->id)->whereIn('status', ['open', 'preparing'])->orderBy('name')->get(['id', 'name', 'capacity', 'occupancy']),
        ]);
    }

    public function store(Request $request)
    {
        $shelter = new Shelter(['province_id' => $this->province()->id]);
        $this->fill($request, $shelter);

        return $this->ok("เพิ่มศูนย์ {$shelter->name} แล้ว");
    }

    public function update(Request $request, Shelter $shelter)
    {
        $this->authorizeProvince($shelter->province_id);
        $this->fill($request, $shelter);
        $shelter->recount();

        return $this->ok("บันทึก {$shelter->name} แล้ว");
    }

    public function status(Request $request, Shelter $shelter)
    {
        $this->authorizeShelter($request, $shelter);
        $status = $request->validate(['status' => ['required', Rule::in(array_keys(ReliefOptions::SHELTER_STATUS))]])['status'];
        if ($status === 'closed' && $shelter->evacuees()->where('status', 'in')->exists()) {
            return back()->with('error', 'ยังมีผู้อพยพในศูนย์ ย้ายหรือบันทึกออกก่อนปิดศูนย์');
        }
        $shelter->fill(['status' => $status]);
        if ($status === 'open' && ! $shelter->opened_at) {
            $shelter->opened_at = now();
        }
        if ($status === 'closed') {
            $shelter->closed_at = now();
        }
        $shelter->save();
        \App\Support\Live::signal($shelter->province_id, 'shelter.changed', ['id' => $shelter->id]);

        return back()->with('success', $shelter->name.': '.$shelter->statusLabel());
    }

    public function destroy(Shelter $shelter)
    {
        $this->authorizeProvince($shelter->province_id);
        if ($shelter->evacuees()->exists()) {
            return back()->with('error', 'ศูนย์นี้มีประวัติผู้อพยพ ปิดศูนย์แทนการลบ');
        }
        $shelter->delete();

        return $this->ok("ลบ {$shelter->name} แล้ว", 'shelters.index');
    }

    public function staff(Request $request, Shelter $shelter)
    {
        $this->authorizeProvince($shelter->province_id);
        $ids = $request->validate(['user_ids' => ['nullable', 'array'], 'user_ids.*' => [Rule::exists('users', 'id')->where('province_id', $shelter->province_id)]])['user_ids'] ?? [];
        $shelter->staff()->sync($ids);

        return back()->with('success', 'กำหนดเจ้าหน้าที่ศูนย์แล้ว');
    }

    /** หน้าลงทะเบียน (รองรับมาจากเคสที่ทีมส่งเข้าศูนย์) */
    public function registerForm(Request $request, Shelter $shelter, ShelterService $service)
    {
        $this->authorizeShelter($request, $shelter);
        $prefill = null;
        if ($request->filled('case')) {
            $case = HelpRequest::inProvince($shelter->province_id)->find($request->integer('case'));
            $prefill = $case ? $service->fromCase($case) + ['case' => $case] : null;
        }

        return view('admin.shelters.register', ['shelter' => $shelter, 'prefill' => $prefill]);
    }

    public function register(Request $request, Shelter $shelter, ShelterService $service)
    {
        $this->authorizeShelter($request, $shelter);
        $data = $request->validate([
            'people' => ['required', 'array', 'min:1', 'max:30'],
            'people.*.name' => ['nullable', 'string', 'max:120'],
            'people.*.phone' => ['nullable', 'string', 'regex:/^[0-9+\s\-]{9,15}$/'],
            'people.*.age_group' => ['nullable', Rule::in(array_keys(ReliefOptions::AGE_GROUPS))],
            'people.*.gender' => ['nullable', Rule::in(array_keys(ReliefOptions::GENDERS))],
            'people.*.needs' => ['nullable', 'array'],
            'people.*.needs.*' => [Rule::in(array_keys(ReliefOptions::EVACUEE_NEEDS))],
            'people.*.note' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'help_request_id' => ['nullable', Rule::exists('help_requests', 'id')->where('province_id', $shelter->province_id)],
            'vulnerable_household_id' => ['nullable', Rule::exists('vulnerable_households', 'id')->where('province_id', $shelter->province_id)],
        ], ['people.*.phone.regex' => 'เบอร์โทรไม่ถูกต้อง'], ['people' => 'รายชื่อ']);

        $people = array_values(array_filter($data['people'], fn ($p) => filled($p['name'] ?? null)));
        if (! $people) {
            return back()->withInput()->with('error', 'ใส่ชื่ออย่างน้อย 1 คน');
        }
        try {
            $list = $service->register($shelter, $people, [
                'address' => $data['address'] ?? null,
                'allow_lookup' => $request->boolean('allow_lookup'),
                'help_request_id' => $data['help_request_id'] ?? null,
                'vulnerable_household_id' => $data['vulnerable_household_id'] ?? null,
            ], $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('shelters.show', $shelter)->with('success', 'ลงทะเบียน '.$list->count().' คน'.($list->first()?->family_code ? ' รหัสครอบครัว '.$list->first()->family_code : ''));
    }

    public function checkout(Request $request, Evacuee $evacuee, ShelterService $service)
    {
        $this->authorizeShelter($request, $evacuee->shelter);
        $data = $request->validate(['reason' => ['required', Rule::in(array_keys(ReliefOptions::OUT_REASONS))]]);
        $n = $service->checkOut($evacuee, ReliefOptions::OUT_REASONS[$data['reason']], $request->user(), $request->boolean('family'));

        return back()->with('success', "บันทึกออกจากศูนย์ {$n} คน");
    }

    public function transfer(Request $request, Evacuee $evacuee, ShelterService $service)
    {
        $this->authorizeShelter($request, $evacuee->shelter);
        $to = Shelter::inProvince($evacuee->province_id)->findOrFail($request->validate(['to' => ['required', 'integer']])['to']);
        try {
            $n = $service->transfer($evacuee, $to, $request->user(), $request->boolean('family'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "ย้าย {$n} คนไป{$to->name}แล้ว");
    }

    public function need(Request $request, Shelter $shelter)
    {
        $this->authorizeShelter($request, $shelter);
        $data = $request->validate([
            'item' => ['required', 'string', 'max:120'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'unit' => ['nullable', 'string', 'max:20'],
            'priority' => ['required', Rule::in(['normal', 'urgent'])],
        ], [], ['item' => 'สิ่งของ']);
        $shelter->needs()->create($data + ['status' => 'open', 'created_by' => $request->user()->id]);

        return back()->with('success', 'เพิ่มรายการที่ต้องการแล้ว');
    }

    public function needDone(Request $request, ShelterNeed $need)
    {
        $this->authorizeShelter($request, $need->shelter);
        $need->update(['status' => $need->status === 'open' ? 'fulfilled' : 'open']);

        return back()->with('success', $need->status === 'fulfilled' ? 'ได้รับแล้ว' : 'เปิดรายการอีกครั้ง');
    }

    protected function authorizeShelter(Request $request, Shelter $shelter): void
    {
        $this->authorizeProvince($shelter->province_id);
        abort_unless(Shelter::forUser($request->user(), $shelter->province_id)->whereKey($shelter->id)->exists(), 403, 'คุณไม่ได้ดูแลศูนย์นี้');
    }

    protected function fill(Request $request, Shelter $shelter): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(ReliefOptions::SHELTER_TYPES))],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['required', 'numeric', 'between:5,21'],
            'lng' => ['required', 'numeric', 'between:97,106'],
            'capacity' => ['required', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in(array_keys(ReliefOptions::SHELTER_STATUS))],
            'facilities' => ['nullable', 'array'],
            'facilities.*' => [Rule::in(array_keys(ReliefOptions::FACILITIES))],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], ['name' => 'ชื่อศูนย์', 'lat' => 'ตำแหน่ง', 'capacity' => 'จำนวนที่รับได้']);

        $shelter->fill($data);
        $shelter->facilities = array_values($data['facilities'] ?? []) ?: null;
        $shelter->is_public = $request->boolean('is_public');
        if ($shelter->status === 'open' && ! $shelter->opened_at) {
            $shelter->opened_at = now();
        }
        $sub = Subdistrict::locate($shelter->province_id, (float) $data['lat'], (float) $data['lng']);
        $shelter->subdistrict_id = $sub?->id;
        $shelter->district_id = $sub?->district_id;
        $shelter->save();
    }
}
