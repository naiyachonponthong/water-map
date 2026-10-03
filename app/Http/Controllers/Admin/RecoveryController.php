<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DamageClaim;
use App\Models\District;
use App\Models\User;
use App\Support\HelpRequestService;
use App\Support\RecoveryOptions;
use App\Support\RecoveryService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * คำร้องเยียวยาหลังน้ำลด: นัดสำรวจ บันทึกผลสำรวจ อนุมัติ จ่าย
 */
class RecoveryController extends Controller
{
    public const TABS = [
        'submitted' => 'รอสำรวจ',
        'surveying' => 'นัดสำรวจแล้ว',
        'surveyed' => 'รอพิจารณา',
        'approved' => 'รอจ่าย',
        'paid' => 'จ่ายแล้ว',
        'rejected' => 'ไม่ผ่าน',
    ];

    public function index(Request $request)
    {
        $province = $this->province();
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'submitted';
        $me = $request->user();

        $base = DamageClaim::inProvince($province->id)
            ->when($request->filled('district'), fn ($q) => $q->where('district_id', $request->integer('district')))
            ->when($request->query('mine') && $me->can('recovery.survey'), fn ($q) => $q->where('surveyor_id', $me->id))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->query('q'));
                $q->where(fn ($w) => $w->where('code', $term)->orWhere('head_name', 'like', "%{$term}%")
                    ->orWhere('phone_hash', HelpRequestService::phoneHash(User::normalizePhone($term))));
            });

        $counts = (clone $base)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');
        $claims = (clone $base)->where('status', $tab)->with('district:id,name_th', 'subdistrict:id,name_th,code', 'surveyor:id,name')
            ->orderByRaw("case house_damage when 'destroyed' then 0 when 'major' then 1 when 'minor' then 2 else 3 end")
            ->orderByDesc('evidence_score')->oldest()
            ->paginate(30)->withQueryString();

        $money = DamageClaim::inProvince($province->id);

        return view('admin.recovery.index', [
            'province' => $province,
            'tab' => $tab,
            'counts' => $counts,
            'claims' => $claims,
            'districts' => District::where('province_id', $province->id)->orderBy('sort')->get(['id', 'name_th']),
            'open' => (bool) Settings::get('recovery_open', $province->id),
            'totals' => [
                'claims' => (clone $money)->count(),
                'approved' => (float) (clone $money)->whereIn('status', ['approved', 'paid'])->sum('approved_amount'),
                'paid' => (float) (clone $money)->where('status', 'paid')->sum('paid_amount'),
                'destroyed' => (clone $money)->where(fn ($q) => $q->where('verified_damage', 'destroyed')->orWhere(fn ($w) => $w->whereNull('verified_damage')->where('house_damage', 'destroyed')))->count(),
            ],
            'rates' => (array) Settings::get('recovery_rates', $province->id),
        ]);
    }

    public function create()
    {
        return view('admin.recovery.create', ['province' => $this->province(), 'levels' => config('floodthai.water_levels')]);
    }

    public function store(Request $request, RecoveryService $service)
    {
        $data = $request->validate(RecoveryService::rules(), ['phone.regex' => 'เบอร์โทรไม่ถูกต้อง'], RecoveryService::attributes());
        [$claim, $existing] = $service->submit($this->province(), $data, ['source' => 'staff', 'user' => $request->user(), 'photos' => $request->file('photos', [])]);

        return redirect()->route('recovery.show', $claim)->with($existing ? 'error' : 'success', $existing ? 'เบอร์นี้มีคำร้องค้างอยู่แล้ว เปิดคำร้องเดิมให้' : 'บันทึกคำร้อง '.$claim->code.' แล้ว');
    }

    public function show(DamageClaim $claim)
    {
        $this->authorizeProvince($claim->province_id);
        $claim->load('events.user:id,name', 'district', 'subdistrict', 'surveyor:id,name', 'helpRequest:id,code,status');

        $nearby = $claim->lat !== null
            ? DamageClaim::inProvince($claim->province_id)->where('id', '!=', $claim->id)
                ->whereBetween('lat', [$claim->lat - 0.0005, $claim->lat + 0.0005])->whereBetween('lng', [$claim->lng - 0.0005, $claim->lng + 0.0005])
                ->limit(5)->get(['id', 'code', 'head_name', 'status'])
            : collect();

        return view('admin.recovery.show', [
            'claim' => $claim,
            'nearby' => $nearby,
            'surveyors' => User::where('province_id', $claim->province_id)->where('status', 'active')->permission('recovery.survey')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function action(Request $request, DamageClaim $claim, RecoveryService $service)
    {
        $this->authorizeProvince($claim->province_id);
        $action = $request->validate(['action' => ['required', Rule::in(['schedule', 'survey', 'approve', 'pay', 'reject', 'evidence'])]])['action'];
        $me = $request->user();
        abort_unless($action === 'survey' ? $me->can('recovery.survey') || $me->can('recovery.manage') : $me->can('recovery.manage'), 403);

        try {
            switch ($action) {
                case 'schedule':
                    $d = $request->validate(['surveyor_id' => ['nullable', Rule::exists('users', 'id')->where('province_id', $claim->province_id)], 'note' => ['nullable', 'string', 'max:255']]);
                    $service->schedule($claim, ! empty($d['surveyor_id']) ? User::find($d['surveyor_id']) : null, $me, $d['note'] ?? null);
                    $msg = 'นัดสำรวจแล้ว';
                    break;
                case 'survey':
                    $d = $request->validate([
                        'verified_damage' => ['required', Rule::in(array_keys(RecoveryOptions::HOUSE))],
                        'water_level' => ['nullable', 'integer', 'between:1,6'],
                        'losses' => ['nullable', 'array'],
                        'losses.*' => [Rule::in(array_keys(RecoveryOptions::LOSSES))],
                        'crop_rai' => ['nullable', 'numeric', 'between:0,100000'],
                        'survey_note' => ['nullable', 'string', 'max:2000'],
                        'survey_photos' => ['nullable', 'array', 'max:5'],
                        'survey_photos.*' => ['image', 'max:8192'],
                    ], [], ['verified_damage' => 'ความเสียหายที่พบจริง']);
                    $service->survey($claim, $d, $me, $request->file('survey_photos', []));
                    $msg = 'บันทึกผลสำรวจแล้ว'.($claim->fresh()->suggested_amount ? ' ยอดแนะนำ '.number_format($claim->fresh()->suggested_amount, 2).' บาท' : '');
                    break;
                case 'approve':
                    $d = $request->validate(['amount' => ['required', 'numeric', 'min:0', 'max:10000000'], 'note' => ['nullable', 'string', 'max:255']], [], ['amount' => 'จำนวนเงิน']);
                    $service->approve($claim, (float) $d['amount'], $me, $d['note'] ?? null);
                    $msg = 'อนุมัติแล้ว';
                    break;
                case 'pay':
                    $d = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'payment_ref' => ['nullable', 'string', 'max:60']], [], ['amount' => 'จำนวนเงิน']);
                    $service->pay($claim, (float) $d['amount'], $d['payment_ref'] ?? null, $me);
                    $msg = 'บันทึกการจ่ายแล้ว';
                    break;
                case 'reject':
                    $d = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'เหตุผล']);
                    $service->reject($claim, $d['reason'], $me);
                    $msg = 'บันทึกว่าไม่ผ่านเกณฑ์แล้ว';
                    break;
                default:
                    $found = $service->evidence($claim);
                    $msg = 'ตรวจหลักฐานใหม่: พบ '.count($found).' รายการ';
            }
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $msg);
    }

    public function rates(Request $request)
    {
        $province = $this->province();
        $num = ['nullable', 'numeric', 'min:0', 'max:10000000'];
        $d = $request->validate([
            'house' => ['array'], 'house.*' => $num,
            'loss' => ['array'], 'loss.*' => $num,
            'crop_per_rai' => $num, 'cap' => $num,
        ]);
        $clean = fn ($arr, $keys) => collect($keys)->mapWithKeys(fn ($k) => [$k => (float) ($arr[$k] ?? 0)])->all();
        Settings::set('recovery_rates', [
            'house' => $clean($d['house'] ?? [], ['minor', 'major', 'destroyed']),
            'loss' => $clean($d['loss'] ?? [], array_keys(RecoveryOptions::LOSSES)),
            'crop_per_rai' => (float) ($d['crop_per_rai'] ?? 0),
            'cap' => (float) ($d['cap'] ?? 0),
        ], $province->id);
        Settings::set('recovery_open', $request->boolean('recovery_open'), $province->id);

        return $this->ok('บันทึกการตั้งค่าโหมดฟื้นฟูแล้ว');
    }
}
