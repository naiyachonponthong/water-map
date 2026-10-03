<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\HelpRequest;
use App\Support\CaseOptions;
use App\Support\HelpRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CaseController extends Controller
{
    public function __construct(protected HelpRequestService $service) {}

    public function index(Request $request)
    {
        $province = $this->province();
        $tab = array_key_exists($request->query('tab'), CaseOptions::TABS) ? $request->query('tab') : 'triage';

        $base = HelpRequest::inProvince($province->id)
            ->when($request->filled('district'), fn ($q) => $q->where('district_id', $request->integer('district')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->query('priority')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->query('source')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->query('q'));
                $digits = preg_replace('/\D+/', '', $term);
                $q->where(fn ($w) => $w->where('code', 'like', '%'.strtoupper($term).'%')
                    ->orWhere('requester_name', 'like', "%$term%")
                    ->orWhere('address_text', 'like', "%$term%")
                    ->when(strlen($digits) >= 9, fn ($x) => $x->orWhere('phone_hash', HelpRequestService::phoneHash($digits)))
                    ->when(strlen($digits) === 4, fn ($x) => $x->orWhere('phone_last4', $digits)));
            });

        $counts = [];
        foreach (CaseOptions::TABS as $key => [, $statuses]) {
            $counts[$key] = $statuses ? (clone $base)->whereIn('status', $statuses)->count() : (clone $base)->count();
        }

        $statuses = CaseOptions::TABS[$tab][1];
        $cases = (clone $base)
            ->with(['district:id,name_th', 'subdistrict:id,name_th,code', 'possibleDuplicateOf:id,code,status'])
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->when(in_array($tab, ['closed', 'done', 'all'], true), fn ($q) => $q->latest(), fn ($q) => $q->urgentFirst())
            ->paginate(30)
            ->withQueryString();

        return view('admin.cases.index', [
            'province' => $province,
            'tab' => $tab,
            'counts' => $counts,
            'cases' => $cases,
            'districts' => District::where('province_id', $province->id)->orderBy('sort')->get(['id', 'name_th']),
            'latestId' => (int) HelpRequest::inProvince($province->id)->max('id'),
        ]);
    }

    /** เคสที่ยังเปิด เป็น GeoJSON สำหรับแผนที่ */
    public function geojson()
    {
        return response()->json(['type' => 'FeatureCollection', 'features' => \App\Support\LiveSnapshot::caseFeatures($this->province()->id)]);
    }

    /** ใช้แจ้งเตือนเคสใหม่บนหน้าจอ (poll ทุก 20 วินาที จนกว่าจะมี realtime) */
    public function poll(Request $request)
    {
        $province = $this->province();
        $since = $request->integer('since');
        $new = HelpRequest::inProvince($province->id)->where('id', '>', $since)->orderBy('id')
            ->get(['id', 'code', 'priority', 'requester_name', 'water_level']);

        return response()->json([
            'latest_id' => (int) ($new->last()->id ?? $since),
            'count' => $new->count(),
            'critical' => $new->where('priority', 'critical')->count(),
            'triage' => HelpRequest::inProvince($province->id)->whereIn('status', ['new', 'screening'])->count(),
            'items' => $new->take(5)->map(fn ($c) => ['code' => $c->code, 'name' => $c->requester_name, 'priority' => $c->priorityLabel(), 'url' => route('cases.show', $c)])->values(),
        ]);
    }

    public function show(HelpRequest $case)
    {
        $this->authorizeProvince($case->province_id);
        $case->load(['assignment.team.leader', 'assignments.team', 'district', 'subdistrict', 'creator:id,name', 'screener:id,name', 'possibleDuplicateOf', 'duplicateOf', 'merged:id,code,duplicate_of_id,requester_name']);

        $nearby = $case->isOpen() ? $this->service->findNearby($case) : null;

        return view('admin.cases.show', [
            'case' => $case,
            'events' => $case->events()->with('user:id,name,avatar_url')->get(),
            'nearby' => $nearby,
            'canManage' => auth()->user()->can('cases.manage'),
        ]);
    }

    public function create(Request $request)
    {
        $province = $this->province();

        return view('admin.cases.form', [
            // เปิดจากรายงานระดับน้ำ: ใส่พิกัดและระดับน้ำให้ก่อน
            'case' => new HelpRequest([
                'water_level' => $request->integer('water_level') ?: 3,
                'people_count' => 1,
                'source' => 'phone',
                'lat' => $request->filled('lat') ? (float) $request->query('lat') : $province->center_lat,
                'lng' => $request->filled('lng') ? (float) $request->query('lng') : $province->center_lng,
                'needs_note' => $request->query('note'),
            ]),
            'province' => $province,
        ]);
    }

    public function store(Request $request)
    {
        $province = $this->province();
        $data = $request->validate(HelpRequestService::rules(true), [], HelpRequestService::attributes());
        $data['location_source'] ??= 'staff';

        [$case] = $this->service->submit($province, $data, [
            'source' => $data['source'],
            'user' => $request->user(),
            'photos' => $request->file('photos', []),
        ]);

        // เจ้าหน้าที่คีย์เองถือว่าคัดกรองแล้ว เข้าคิวรอทีมทันที
        if ($request->boolean('queue_now')) {
            $this->service->changeStatus($case, 'queued', $request->user(), 'เจ้าหน้าที่รับแจ้งและคัดกรองแล้ว');
        }

        return redirect()->route('cases.show', $case)->with('success', "บันทึกเคส {$case->code} แล้ว");
    }

    public function edit(HelpRequest $case)
    {
        $this->authorizeProvince($case->province_id);

        return view('admin.cases.form', ['case' => $case, 'province' => $case->province]);
    }

    public function update(Request $request, HelpRequest $case)
    {
        $this->authorizeProvince($case->province_id);
        $rules = HelpRequestService::rules(true);
        unset($rules['source']);
        $rules['requester_phone'][0] = 'nullable';
        $data = $request->validate($rules, [], HelpRequestService::attributes());

        $before = $case->only(['lat', 'lng', 'water_level', 'people_count', 'vulnerable', 'needs', 'address_text', 'requester_name']);
        $fill = collect($data)->except(['photos', 'requester_phone', 'contact_phone'])->all();
        $fill['vulnerable'] = $data['vulnerable'] ?? [];
        $fill['needs'] = $data['needs'] ?? [];
        $case->fill($fill);
        if (! empty($data['requester_phone'])) {
            $phone = \App\Models\User::normalizePhone($data['requester_phone']);
            $case->fill(['requester_phone' => $phone, 'phone_hash' => HelpRequestService::phoneHash($phone), 'phone_last4' => substr($phone, -4)]);
        }
        $case->contact_phone = ! empty($data['contact_phone']) ? \App\Models\User::normalizePhone($data['contact_phone']) : null;
        $this->service->applyPriority($case);
        $case->save();

        $after = $case->only(array_keys($before));
        $changed = array_keys(array_filter($after, fn ($v, $k) => $before[$k] != $v, ARRAY_FILTER_USE_BOTH));
        $this->service->event($case, 'edit', ['user' => $request->user(), 'note' => $changed ? 'แก้ไข: '.implode(', ', $changed) : null]);

        return redirect()->route('cases.show', $case)->with('success', 'บันทึกการแก้ไขแล้ว');
    }

    /** รับเคสเข้าคัดกรอง / ผ่านคัดกรอง เข้าคิวรอทีม */
    public function screen(Request $request, HelpRequest $case)
    {
        $this->authorizeProvince($case->province_id);
        $data = $request->validate([
            'action' => ['required', Rule::in(['start', 'pass'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['action'] === 'start' && $case->status === 'new') {
            $this->service->changeStatus($case, 'screening', $request->user(), $data['note'] ?? null);

            return back()->with('success', "รับ {$case->code} มาคัดกรองแล้ว");
        }
        if ($data['action'] === 'pass' && in_array($case->status, ['new', 'screening'], true)) {
            $this->service->changeStatus($case, 'queued', $request->user(), $data['note'] ?? 'ผ่านการคัดกรอง');

            return back()->with('success', "{$case->code} เข้าคิวรอทีมแล้ว");
        }

        return back()->with('error', 'สถานะเคสเปลี่ยนไปแล้ว รีเฟรชหน้าเพื่อดูล่าสุด');
    }

    /** เปลี่ยนสถานะด้วยมือ (ใช้ก่อนมีโมดูลทีม และใช้แก้สถานะที่ผิด) */
    public function status(Request $request, HelpRequest $case)
    {
        $this->authorizeProvince($case->province_id);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(CaseOptions::STATUSES))],
            'note' => ['nullable', 'string', 'max:500'],
            'outcome' => [Rule::requiredIf(in_array($request->input('status'), ['rescued', 'closed', 'cancelled'], true)), 'nullable', Rule::in(array_keys(CaseOptions::OUTCOMES))],
            'people_rescued' => ['nullable', 'integer', 'between:0,500'],
        ], [], ['status' => 'สถานะ', 'outcome' => 'ผลการช่วยเหลือ']);

        abort_if($data['status'] === 'merged', 422, 'ใช้ปุ่มรวมเคสแทน');

        // มีทีมทำงานอยู่: ขั้นตอนของทีมต้องอัปเดตผ่านปุ่มของทีม ส่วนการถอยกลับคิวให้ยกเลิกงานทีมก่อน
        $active = $case->assignment && $case->assignment->isActive() ? $case->assignment : null;
        if ($active && in_array($data['status'], ['offered', 'accepted', 'en_route', 'on_site'], true)) {
            return back()->with('error', 'เคสนี้มีทีมรับอยู่ ใช้ปุ่มในส่วนทีมที่รับงานเพื่ออัปเดตความคืบหน้า');
        }
        if ($active && in_array($data['status'], ['new', 'screening', 'queued'], true)) {
            app(\App\Support\DispatchService::class)->cancel($active, $request->user(), $data['note'] ?? 'ศูนย์ถอยเคสกลับ');
            $case->refresh();
        }
        if (! $active && in_array($data['status'], ['offered', 'accepted', 'en_route', 'on_site'], true)) {
            return back()->with('error', 'มอบหมายทีมก่อน จึงจะเปลี่ยนเป็นสถานะของทีมได้');
        }

        $extra = array_filter([
            'outcome' => $data['outcome'] ?? null,
            'people_rescued' => $data['people_rescued'] ?? null,
            'close_note' => $data['note'] ?? null,
        ], fn ($v) => $v !== null);
        if (! in_array($data['status'], CaseOptions::FINAL, true)) {
            $extra = [];
        }

        $this->service->changeStatus($case, $data['status'], $request->user(), $data['note'] ?? null, $extra);

        return back()->with('success', "{$case->code}: ".CaseOptions::status($data['status']));
    }

    public function priority(Request $request, HelpRequest $case)
    {
        $this->authorizeProvince($case->province_id);
        $data = $request->validate([
            'priority' => ['required', Rule::in([...array_keys(CaseOptions::PRIORITIES), 'auto'])],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $from = $case->priority;
        if ($data['priority'] === 'auto') {
            $case->priority_locked = false;
        } else {
            $case->priority_locked = true;
            $case->priority = $data['priority'];
        }
        $this->service->applyPriority($case);
        $case->save();

        $this->service->event($case, 'priority', [
            'user' => $request->user(),
            'note' => trim(CaseOptions::priority($from).' → '.$case->priorityLabel().($case->priority_locked ? ' (กำหนดเอง)' : ' (คำนวณอัตโนมัติ)').'. '.($data['note'] ?? '')),
        ]);

        return back()->with('success', 'ปรับความเร่งด่วนแล้ว');
    }

    public function merge(Request $request, HelpRequest $case)
    {
        $this->authorizeProvince($case->province_id);
        $code = strtoupper(trim((string) $request->validate(['into' => ['required', 'string']], [], ['into' => 'เลขเคสหลัก'])['into']));

        $into = HelpRequest::inProvince($case->province_id)->where('code', $code)->first();
        if (! $into || $into->id === $case->id) {
            return back()->withErrors(['into' => 'ไม่พบเคส '.$code.' ในจังหวัดนี้']);
        }
        if (! $into->isOpen()) {
            return back()->withErrors(['into' => "เคส {$into->code} ปิดไปแล้ว"]);
        }

        $this->service->merge($case, $into, $request->user());

        return redirect()->route('cases.show', $into)->with('success', "รวม {$case->code} เข้ากับ {$into->code} แล้ว");
    }

    /** ไม่ใช่เคสซ้ำ ลบธงเตือน */
    public function notDuplicate(Request $request, HelpRequest $case)
    {
        $this->authorizeProvince($case->province_id);
        $case->update(['possible_duplicate_of_id' => null]);
        $this->service->event($case, 'note', ['user' => $request->user(), 'note' => 'ตรวจแล้ว ไม่ใช่เคสซ้ำ']);

        return back()->with('success', 'ยืนยันว่าไม่ใช่เคสซ้ำแล้ว');
    }

    public function note(Request $request, HelpRequest $case)
    {
        $this->authorizeProvince($case->province_id);
        $data = $request->validate([
            'type' => ['required', Rule::in(['note', 'call'])],
            'note' => ['required', 'string', 'max:1000'],
            'public' => ['nullable', 'boolean'],
        ], [], ['note' => 'ข้อความ']);

        $this->service->event($case, $data['type'], [
            'user' => $request->user(),
            'note' => $data['note'],
            'public' => $request->boolean('public'),
        ]);

        return back()->with('success', 'บันทึกแล้ว');
    }
}
