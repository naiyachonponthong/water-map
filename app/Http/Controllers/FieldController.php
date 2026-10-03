<?php

namespace App\Http\Controllers;

use App\Models\HelpRequest;
use App\Models\Team;
use App\Support\FieldService;
use App\Support\Live;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * แอปภาคสนาม (PWA) ของทีมกู้ภัย: หน้าเดียว + JSON API
 */
class FieldController extends Controller
{
    public function __construct(protected FieldService $field) {}

    public function app(Request $request)
    {
        $team = $this->team($request, false);

        return view('field.app', [
            'team' => $team,
            'options' => [
                'outcomes' => collect(\App\Support\CaseOptions::OUTCOMES)->except(['duplicate', 'self_safe']),
                'decline' => \App\Support\TeamOptions::DECLINE_REASONS,
                'levels' => collect(config('floodthai.water_levels'))->map(fn ($l) => ['label' => $l['label'], 'short' => $l['short'], 'color' => $l['color']]),
                'sos' => collect(\App\Models\TeamSos::KINDS)->map(fn ($k) => $k[0]),
                'team_status' => collect(\App\Support\TeamOptions::STATUSES)->map(fn ($s) => ['label' => $s[0], 'color' => $s[2]]),
                'check' => collect(\App\Support\RiskOptions::CHECK)->map(fn ($c) => $c[0]),
                'trends' => collect(\App\Support\ReportOptions::TRENDS)->map(fn ($t) => $t[0]),
            ],
        ]);
    }

    public function state(Request $request)
    {
        return response()->json($this->field->state($this->team($request)))->header('Cache-Control', 'no-store');
    }

    public function action(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'uuid'],
            'type' => ['required', Rule::in(FieldService::TYPES)],
            'payload' => ['nullable', 'array'],
            'client_at' => ['nullable', 'date'],
        ]);

        $result = $this->field->apply($request->user(), $this->team($request), $data['key'], $data['type'], $data['payload'] ?? [], $data['client_at'] ?? null);

        return response()->json($result);
    }

    public function location(Request $request)
    {
        $team = $this->team($request);
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:5,21'],
            'lng' => ['required', 'numeric', 'between:97,106'],
        ]);
        $team->forceFill(['last_lat' => $data['lat'], 'last_lng' => $data['lng'], 'last_seen_at' => now()])->saveQuietly();
        Live::teamChanged($team);

        return response()->json(['ok' => true]);
    }

    /** แนบรูปจากหน้างานเข้าเคส (ต้องออนไลน์) */
    public function photo(Request $request)
    {
        $team = $this->team($request);
        $data = $request->validate([
            'case_id' => ['required', 'integer'],
            'photo' => ['required', 'image', 'max:8192'],
        ]);
        $case = HelpRequest::findOrFail($data['case_id']);
        abort_unless($case->team_id === $team->id, 403, 'เคสนี้ไม่ได้อยู่กับทีมคุณ');

        $path = $request->file('photo')->store('help/'.now()->format('Y/m'), 'public');
        $case->photos = array_slice([...($case->photos ?? []), $path], -12);
        $case->save();
        app(\App\Support\HelpRequestService::class)->event($case, 'note', ['user' => $request->user(), 'note' => 'ทีม '.$team->name.' แนบรูปจากหน้างาน']);

        return response()->json(['ok' => true, 'message' => 'แนบรูปแล้ว']);
    }

    protected function team(Request $request, bool $required = true): ?Team
    {
        $team = $request->user()->team()->first();
        abort_if($required && ! $team, 403, 'คุณยังไม่ได้อยู่ในทีม');

        return $team;
    }
}
