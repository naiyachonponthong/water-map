<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ExternalLink;
use App\Support\Settings;
use App\Support\Theme;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public const TABS = [
        'general' => ['ทั่วไป', 'sliders'],
        'switches' => ['สวิตช์ศูนย์', 'toggles'],
        'operation' => ['การทำงาน', 'speedometer2'],
        'contacts' => ['เบอร์ฉุกเฉิน', 'telephone'],
        'links' => ['ลิงก์ภายนอก', 'link-45deg'],
        'line' => ['LINE OA', 'chat-dots'],
    ];

    public function index(Request $request)
    {
        $province = $this->province();
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'general';
        $pid = $province->id;

        return view('admin.settings.index', [
            'province' => $province,
            'tab' => $tab,
            'tabs' => self::TABS,
            'presets' => Theme::PRESETS,
            's' => [
                'theme_color' => Settings::get('theme_color', $pid),
                'hotline' => Settings::get('hotline', $pid),
                'public_notice' => Settings::get('public_notice', $pid, ''),
                'center_contact' => Settings::get('center_contact', null, ''),
                'privacy_controller' => Settings::get('privacy_controller', $pid, ''),
                'privacy_contact' => Settings::get('privacy_contact', $pid, ''),
                'privacy_extra' => Settings::get('privacy_extra', $pid, ''),
                'team_self_assign' => (bool) Settings::get('team_self_assign', $pid),
                'reports_open' => (bool) Settings::get('reports_open', $pid),
                'report_premoderate' => (bool) Settings::get('report_premoderate', $pid),
                'duplicate_radius_m' => Settings::get('duplicate_radius_m', $pid),
                'duplicate_window_hours' => Settings::get('duplicate_window_hours', $pid),
                'report_expire_hours' => Settings::get('report_expire_hours', $pid),
                'risk_alert_radius_m' => Settings::get('risk_alert_radius_m', $pid),
                'offer_timeout_min' => Settings::get('offer_timeout_min', $pid),
                'household_trigger_level' => Settings::get('household_trigger_level', $pid),
                'proactive_cooldown_hours' => Settings::get('proactive_cooldown_hours', $pid),
                'rain_warning_mm' => Settings::get('rain_warning_mm', $pid),
                'rain_critical_mm' => Settings::get('rain_critical_mm', $pid),
                'line_ready' => \App\Support\LineMessenger::configured($pid),
                'line_secret_set' => filled(\App\Support\LineMessenger::channelSecret($pid)),
                'line_oa_id' => Settings::get('line_oa_id', $pid),
                'priority_weights' => array_replace(config('floodthai.defaults.priority_weights'), (array) Settings::get('priority_weights', $pid)),
            ],
            'contacts' => $province->emergencyContacts()->get(),
            'links' => ExternalLink::where('province_id', $pid)->orWhereNull('province_id')->orderByRaw('province_id is null')->orderBy('sort')->get(),
        ]);
    }

    public function general(Request $request)
    {
        $province = $this->province();
        $data = $request->validate([
            'theme_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'hotline' => ['required', 'string', 'max:20'],
            'public_notice' => ['nullable', 'string', 'max:300'],
            'center_lat' => ['nullable', 'numeric', 'between:5,21'],
            'center_lng' => ['nullable', 'numeric', 'between:97,106'],
            'default_zoom' => ['required', 'integer', 'between:7,15'],
            'center_contact' => ['nullable', 'string', 'max:150'],
            'privacy_controller' => ['nullable', 'string', 'max:200'],
            'privacy_contact' => ['nullable', 'string', 'max:300'],
            'privacy_extra' => ['nullable', 'string', 'max:3000'],
        ], [], ['theme_color' => 'สีหลัก', 'hotline' => 'สายด่วนหลัก', 'center_lat' => 'ละติจูด', 'center_lng' => 'ลองจิจูด']);

        Settings::set('theme_color', Theme::normalize($data['theme_color']), $province->id);
        Settings::set('hotline', preg_replace('/[^\d-]/', '', $data['hotline']), $province->id);
        Settings::set('public_notice', $data['public_notice'] ?? '', $province->id);
        foreach (['privacy_controller', 'privacy_contact', 'privacy_extra'] as $k) {
            Settings::set($k, $data[$k] ?? '', $province->id);
        }
        $province->update([
            'center_lat' => $data['center_lat'],
            'center_lng' => $data['center_lng'],
            'default_zoom' => $data['default_zoom'],
        ]);

        // ผู้ติดต่อขอเปิดศูนย์ เป็นค่าระดับระบบ แก้ได้เฉพาะผู้ดูแลระบบสูงสุด
        if ($request->user()->isSuperAdmin() && $request->has('center_contact')) {
            Settings::set('center_contact', $data['center_contact'] ?? '');
        }

        return $this->ok('บันทึกการตั้งค่าทั่วไปแล้ว');
    }

    public function switches(Request $request)
    {
        $province = $this->province();
        $me = $request->user();

        $commandOpen = $me->isSuperAdmin() ? $request->boolean('command_open') : $province->command_open;
        $webHelp = $commandOpen && $request->boolean('web_help_open');

        $changes = [];
        if ($commandOpen !== $province->command_open) {
            $changes['command_open'] = $commandOpen;
            if ($commandOpen) {
                $changes['command_opened_at'] = now();
            }
        }
        if ($webHelp !== $province->web_help_open) {
            $changes['web_help_open'] = $webHelp;
        }
        if ($changes) {
            $province->update($changes);
            AuditLog::record('switch', $province, null, $changes, 'เปลี่ยนสวิตช์ศูนย์ '.$province->fullName());
        }

        Settings::set('team_self_assign', $request->boolean('team_self_assign'), $province->id);
        Settings::set('reports_open', $request->boolean('reports_open'), $province->id);
        Settings::set('report_premoderate', $request->boolean('report_premoderate'), $province->id);

        return $this->ok('บันทึกสวิตช์ศูนย์แล้ว', 'admin.settings.index', ['tab' => 'switches']);
    }

    public function operation(Request $request)
    {
        $province = $this->province();
        $data = $request->validate([
            'duplicate_radius_m' => ['required', 'integer', 'between:20,2000'],
            'duplicate_window_hours' => ['required', 'integer', 'between:1,72'],
            'report_expire_hours' => ['required', 'integer', 'between:6,168'],
            'risk_alert_radius_m' => ['required', 'integer', 'between:100,10000'],
            'offer_timeout_min' => ['required', 'integer', 'between:2,120'],
            'household_trigger_level' => ['required', 'integer', 'between:1,6'],
            'proactive_cooldown_hours' => ['required', 'integer', 'between:1,168'],
            'rain_warning_mm' => ['required', 'integer', 'between:5,500'],
            'rain_critical_mm' => ['required', 'integer', 'between:10,1000', 'gte:rain_warning_mm'],
            'priority_weights' => ['required', 'array'],
            'priority_weights.*' => ['required', 'integer', 'between:0,100'],
        ], [], ['duplicate_radius_m' => 'รัศมีเคสซ้ำ', 'report_expire_hours' => 'อายุรายงาน']);

        foreach (['duplicate_radius_m', 'duplicate_window_hours', 'report_expire_hours', 'risk_alert_radius_m', 'offer_timeout_min', 'household_trigger_level', 'proactive_cooldown_hours', 'rain_warning_mm', 'rain_critical_mm'] as $k) {
            Settings::set($k, (int) $data[$k], $province->id);
        }
        $weights = array_intersect_key(array_map('intval', $data['priority_weights']), config('floodthai.defaults.priority_weights'));
        Settings::set('priority_weights', $weights, $province->id);

        return $this->ok('บันทึกค่าการทำงานแล้ว', 'admin.settings.index', ['tab' => 'operation']);
    }

    public function line(Request $request)
    {
        $province = $this->province();
        $data = $request->validate([
            'line_oa_id' => ['nullable', 'string', 'max:40', 'regex:/^@?[\w.\-]+$/'],
            'line_oa_token' => ['nullable', 'string', 'max:300'],
            'line_oa_secret' => ['nullable', 'string', 'max:100'],
        ], [], ['line_oa_id' => 'LINE ID', 'line_oa_token' => 'Channel access token', 'line_oa_secret' => 'Channel secret']);

        Settings::set('line_oa_id', $data['line_oa_id'] ?? null, $province->id);
        // ช่องรหัสเว้นว่าง = ใช้ค่าเดิม, ติ๊กล้าง = ลบ
        if ($request->boolean('clear')) {
            \App\Support\LineMessenger::saveSecret('line_oa_token', null, $province->id);
            \App\Support\LineMessenger::saveSecret('line_oa_secret', null, $province->id);
        } else {
            foreach (['line_oa_token', 'line_oa_secret'] as $k) {
                if (filled($data[$k] ?? null)) {
                    \App\Support\LineMessenger::saveSecret($k, $data[$k], $province->id);
                }
            }
        }
        AuditLog::record('updated', $province, null, ['line_oa' => $request->boolean('clear') ? 'cleared' : 'updated'], 'ตั้งค่า LINE OA');

        return $this->ok('บันทึกการตั้งค่า LINE OA แล้ว', 'admin.settings.index', ['tab' => 'line']);
    }
}
