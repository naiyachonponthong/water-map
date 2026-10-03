<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Subdistrict;
use App\Models\User;
use App\Models\VulnerableHousehold;
use App\Support\RiskEngine;
use App\Support\RiskImporter;
use App\Support\RiskOptions;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * ทะเบียนครัวเรือนกลุ่มเปราะบาง (เห็นเฉพาะผู้มีสิทธิ์ vulnerable.manage)
 */
class HouseholdController extends Controller
{
    public function index(Request $request)
    {
        $province = $this->province();
        $households = VulnerableHousehold::inProvince($province->id)
            ->with('district:id,name_th', 'subdistrict:id,name_th,code', 'openCase:id,code,status')
            ->when($request->filled('district'), fn ($q) => $q->where('district_id', $request->integer('district')))
            ->when($request->filled('condition'), fn ($q) => $q->whereJsonContains('conditions', $request->query('condition')))
            ->when($request->query('check') === 'never', fn ($q) => $q->whereNull('last_checked_at'))
            ->when(in_array($request->query('check'), array_keys(RiskOptions::CHECK), true), fn ($q) => $q->where('last_check_status', $request->query('check')))
            ->when($request->query('consent') === 'no', fn ($q) => $q->whereNull('consent_at'))
            ->when($request->query('active') !== 'all', fn ($q) => $q->where('is_active', true))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('head_name', 'like', '%'.$request->query('q').'%')->orWhere('address', 'like', '%'.$request->query('q').'%')))
            ->orderByRaw('open_case_id is null')
            ->orderBy('head_name')
            ->paginate(30)->withQueryString();

        $stats = [
            'total' => VulnerableHousehold::inProvince($province->id)->where('is_active', true)->count(),
            'open' => VulnerableHousehold::inProvince($province->id)->whereNotNull('open_case_id')->whereHas('openCase', fn ($q) => $q->open())->count(),
            'checked_today' => VulnerableHousehold::inProvince($province->id)->where('last_checked_at', '>=', today())->count(),
            'no_location' => VulnerableHousehold::inProvince($province->id)->where('is_active', true)->whereNull('lat')->count(),
        ];

        return view('admin.households.index', [
            'province' => $province,
            'households' => $households,
            'stats' => $stats,
            'districts' => District::where('province_id', $province->id)->orderBy('sort')->get(['id', 'name_th']),
            'trigger' => (int) Settings::get('household_trigger_level', $province->id),
        ]);
    }

    public function create()
    {
        return $this->form(new VulnerableHousehold(['members' => 1, 'is_active' => true]), $this->province());
    }

    public function store(Request $request)
    {
        $province = $this->province();
        $h = new VulnerableHousehold(['province_id' => $province->id, 'created_by' => $request->user()->id]);
        $this->fill($request, $h);

        return redirect()->route('vulnerable.show', $h)->with('success', "เพิ่ม {$h->head_name} แล้ว");
    }

    public function show(VulnerableHousehold $household)
    {
        $this->authorizeProvince($household->province_id);
        $household->load('checks.user:id,name', 'checks.team:id,name', 'district', 'subdistrict', 'openCase');

        return view('admin.households.show', [
            'h' => $household,
            'cases' => \App\Models\HelpRequest::where('household_id', $household->id)->latest()->limit(10)->get(),
        ]);
    }

    public function edit(VulnerableHousehold $household)
    {
        $this->authorizeProvince($household->province_id);

        return $this->form($household, $household->province);
    }

    public function update(Request $request, VulnerableHousehold $household)
    {
        $this->authorizeProvince($household->province_id);
        $this->fill($request, $household);

        return redirect()->route('vulnerable.show', $household)->with('success', 'บันทึกแล้ว');
    }

    public function destroy(VulnerableHousehold $household)
    {
        $this->authorizeProvince($household->province_id);
        $household->delete();

        return $this->ok("ลบ {$household->head_name} ออกจากทะเบียนแล้ว", 'vulnerable.index');
    }

    public function check(Request $request, VulnerableHousehold $household, RiskEngine $engine)
    {
        $this->authorizeProvince($household->province_id);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(RiskOptions::CHECK))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $engine->recordCheck($household, $data['status'], $request->user(), null, $data['note'] ?? null);

        return back()->with('success', 'บันทึกผลเยี่ยม '.$household->head_name.': '.RiskOptions::CHECK[$data['status']][0]);
    }

    /** เปิดเคสตรวจเยี่ยมเชิงรุกทันที ไม่ต้องรอระดับน้ำ */
    public function openCase(Request $request, VulnerableHousehold $household, RiskEngine $engine)
    {
        $this->authorizeProvince($household->province_id);
        if ($household->openCase?->isOpen()) {
            return redirect()->route('cases.show', $household->openCase)->with('error', 'มีเคสเปิดอยู่แล้ว');
        }
        [$lat] = $engine->householdPosition($household);
        if ($lat === null) {
            return back()->with('error', 'ใส่พิกัดบ้านหรือตำบลก่อน ทีมจะได้ไปถูก');
        }
        $case = $engine->openProactiveCase($household->province, $household, 2, 'ศูนย์สั่งตรวจเยี่ยม', $request->user());

        return redirect()->route('cases.show', $case)->with('success', "เปิดเคส {$case->code} แล้ว");
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']], [], ['file' => 'ไฟล์']);
        $report = (new RiskImporter($this->province(), $request->user()))->households($request->file('file'));

        return back()->with('success', "นำเข้า {$report['created']} ครัวเรือน".($report['skipped'] ? " ข้าม {$report['skipped']}" : ''))
            ->with('import_errors', array_slice($report['errors'], 0, 20));
    }

    /** ไฟล์ตัวอย่างสำหรับนำเข้า */
    public function template()
    {
        $csv = "\xEF\xBB\xBF".implode("\n", [
            'ชื่อ,เบอร์,ที่อยู่,ตำบล,อำเภอ,ละติจูด,ลองจิจูด,จำนวนคน,ภาวะ,ผู้ดูแล,เบอร์ผู้ดูแล,ยินยอม,หมายเหตุ',
            'นางสมใจ ใจดี,0812345678,45 หมู่ 3,บางพระ,บางคล้า,13.7215,101.2034,2,"ผู้ป่วยติดเตียง,ผู้สูงอายุอยู่ลำพัง",นายอสม ใจบุญ,0898765432,ใช่,ต้องใช้เปล',
        ]);

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="households-template.csv"']);
    }

    protected function form(VulnerableHousehold $h, $province)
    {
        return view('admin.households.form', [
            'h' => $h,
            'province' => $province,
            'subdistricts' => Subdistrict::where('province_id', $province->id)->with('district:id,name_th')->orderBy('name_th')->get(['id', 'district_id', 'name_th']),
        ]);
    }

    protected function fill(Request $request, VulnerableHousehold $h): void
    {
        $data = $request->validate([
            'head_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'subdistrict_id' => ['nullable', Rule::exists('subdistricts', 'id')->where('province_id', $h->province_id)],
            'lat' => ['nullable', 'numeric', 'between:5,21', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:97,106', 'required_with:lat'],
            'members' => ['required', 'integer', 'between:1,50'],
            'conditions' => ['required', 'array', 'min:1'],
            'conditions.*' => [Rule::in(array_keys(RiskOptions::CONDITIONS))],
            'note' => ['nullable', 'string', 'max:1000'],
            'caretaker_name' => ['nullable', 'string', 'max:120'],
            'caretaker_phone' => ['nullable', 'string', 'max:20'],
            'consent' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [], ['head_name' => 'ชื่อ', 'conditions' => 'ภาวะ', 'members' => 'จำนวนคน', 'lat' => 'ละติจูด']);

        $sub = ! empty($data['subdistrict_id']) ? Subdistrict::find($data['subdistrict_id']) : null;
        if (! $sub && ! empty($data['lat'])) {
            $sub = Subdistrict::locate($h->province_id, (float) $data['lat'], (float) $data['lng']);
        }

        $h->fill([
            'head_name' => $data['head_name'],
            'phone' => ! empty($data['phone']) ? User::normalizePhone($data['phone']) : null,
            'address' => $data['address'] ?? null,
            'subdistrict_id' => $sub?->id,
            'district_id' => $sub?->district_id,
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'members' => $data['members'],
            'conditions' => array_values($data['conditions']),
            'note' => $data['note'] ?? null,
            'caretaker_name' => $data['caretaker_name'] ?? null,
            'caretaker_phone' => ! empty($data['caretaker_phone']) ? User::normalizePhone($data['caretaker_phone']) : null,
            'is_active' => $request->boolean('is_active', true),
        ]);
        if ($request->boolean('consent') && ! $h->consent_at) {
            $h->consent_at = now();
            $h->consent_by = $request->user()->id;
        } elseif (! $request->boolean('consent')) {
            $h->consent_at = null;
            $h->consent_by = null;
        }
        $h->save();
    }
}
