<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\District;
use App\Support\ExportService;
use App\Support\ReportService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * รายงานผู้บริหาร, รายงานสถานการณ์ (พิมพ์/PDF) และส่งออกข้อมูล
 */
class ExportController extends Controller
{
    public function index(Request $request, ReportService $reports)
    {
        $province = $this->province();
        [$from, $to] = $this->range($request);
        $district = $request->integer('district') ?: null;

        return view('admin.exports.index', [
            'province' => $province,
            'r' => $reports->build($province, $from, $to, $district),
            'from' => $from,
            'to' => $to,
            'districts' => District::where('province_id', $province->id)->orderBy('sort')->get(['id', 'name_th']),
            'datasets' => collect(ExportService::DATASETS)->filter(fn ($d) => $request->user()->can($d[1])),
        ]);
    }

    public function sitrep(Request $request, ReportService $reports)
    {
        $province = $this->province();
        [$from, $to] = $this->range($request);

        return view('admin.exports.sitrep', [
            'province' => $province,
            'r' => $reports->build($province, $from, $to),
            'from' => $from,
            'to' => $to,
            'hotline' => Settings::get('hotline', $province->id),
            'title' => Settings::get('app_title', null, config('app.name')),
        ]);
    }

    public function download(Request $request, string $dataset, ExportService $export)
    {
        abort_unless(array_key_exists($dataset, ExportService::DATASETS), 404);
        abort_unless($request->user()->can(ExportService::DATASETS[$dataset][1]), 403);
        $province = $this->province();
        [$from, $to] = $this->range($request);

        $phones = $request->boolean('phones')
            && isset(ExportService::PHONE_PERMISSION[$dataset])
            && $request->user()->can(ExportService::PHONE_PERMISSION[$dataset]);

        AuditLog::record('exported', $province, null, ['dataset' => $dataset, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'phones' => $phones],
            'ส่งออก'.ExportService::DATASETS[$dataset][0].($phones ? ' (รวมเบอร์โทร)' : ''));

        return $export->stream($dataset, $province, $from, $to, $phones);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function range(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        [$defFrom, $defTo] = ReportService::defaultRange($this->province());
        $tz = config('app.timezone');
        $from = $request->filled('from') ? Carbon::parse($request->query('from'), $tz)->startOfDay() : $defFrom;
        $to = $request->filled('to') ? Carbon::parse($request->query('to'), $tz)->endOfDay() : $defTo;
        if ($from->diffInDays($to, true) > 366) {
            $from = $to->copy()->subDays(366)->startOfDay();
        }

        return [$from, $to];
    }
}
