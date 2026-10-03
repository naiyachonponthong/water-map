<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Support\CaseOptions;
use App\Support\Geo;
use App\Support\HelpRequestService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * ฟอร์มขอความช่วยเหลือของประชาชน (ไม่ต้องล็อกอิน)
 */
class HelpController extends Controller
{
    public function form(Request $request, Province $province)
    {
        abort_unless($province->is_active, 404);

        $common = [
            'province' => $province,
            'contacts' => $province->publicContacts(),
            'hotline' => Settings::get('hotline', $province->id),
        ];

        if (! $province->web_help_open) {
            return view('public.help.closed', $common);
        }

        $response = response()->view('public.help.form', $common + [
            'levels' => config('floodthai.water_levels'),
            'vulnerable' => CaseOptions::VULNERABLE,
            'needs' => CaseOptions::NEEDS,
        ]);

        // รหัสอุปกรณ์ใช้กันส่งรัว (ไม่ผูกกับตัวตน)
        if (! $request->cookie('fdev')) {
            $response->cookie('fdev', (string) Str::uuid(), 60 * 24 * 365);
        }

        return $response;
    }

    public function store(Request $request, Province $province, HelpRequestService $service)
    {
        abort_unless($province->is_active, 404);
        if (! $province->web_help_open) {
            return redirect()->route('public.help', $province)->with('error', 'ขณะนี้ยังไม่รับแจ้งทางเว็บ โทรเบอร์ฉุกเฉินแทน');
        }

        // กับดักบอท: ช่องนี้ซ่อนไว้ คนจริงไม่กรอก
        if (filled($request->input('website'))) {
            return redirect()->route('public.provinces');
        }

        $data = $request->validate(HelpRequestService::rules(), [
            'lat.required' => 'กรุณาระบุตำแหน่ง',
            'needs.required' => 'เลือกสิ่งที่ต้องการอย่างน้อย 1 ข้อ',
            'requester_phone.regex' => 'เบอร์โทรไม่ถูกต้อง',
        ], HelpRequestService::attributes());

        [$req, $attached] = $service->submit($province, $data, [
            'source' => 'web',
            'photos' => $request->file('photos', []),
            'device_hash' => $request->cookie('fdev') ? hash('sha256', $request->cookie('fdev')) : null,
            'ip' => $request->ip(),
        ]);

        return redirect()->to($req->trackUrl())
            ->with($attached ? 'attached' : 'submitted', true);
    }

    /**
     * แปลงลิงก์ที่ผู้ใช้วาง (Google Maps ทั้งแบบเต็มและแบบย่อ, ข้อความพิกัด) เป็นพิกัด
     */
    public function resolve(Request $request)
    {
        $text = trim((string) $request->input('text'));
        if ($text === '' || mb_strlen($text) > 500) {
            return response()->json(['ok' => false, 'message' => 'วางลิงก์หรือพิกัด'], 422);
        }

        if ($ll = Geo::parseLatLng($text)) {
            return response()->json(['ok' => true, 'lat' => $ll[0], 'lng' => $ll[1]]);
        }

        // ลิงก์ย่อ ต้องตามไปดูปลายทาง (อนุญาตเฉพาะโดเมนของ Google Maps ทั้งต้นทางและทุกจุดที่ redirect ไป)
        $allowed = fn (string $host) => (bool) preg_match('/(^|\.)(goo\.gl|google\.com|google\.co\.th|g\.co)$/i', $host);
        if (preg_match('~https://[^\s/]+/\S+~i', $text, $m) && $allowed((string) parse_url($m[0], PHP_URL_HOST))) {
            try {
                $res = Http::timeout(6)->withOptions(['allow_redirects' => [
                    'max' => 5,
                    'protocols' => ['https'],
                    'track_redirects' => true,
                    'on_redirect' => function ($req, $res, $uri) use ($allowed) {
                        if (! $allowed($uri->getHost())) {
                            throw new \RuntimeException('redirect ออกนอก Google Maps');
                        }
                    },
                ]])->get($m[0]);
                $history = $res->header('X-Guzzle-Redirect-History');
                $candidates = array_filter([...(explode(', ', (string) $history)), mb_substr($res->body(), 0, 200000)]);
                foreach (array_reverse($candidates) as $c) {
                    if ($ll = Geo::parseLatLng($c)) {
                        return response()->json(['ok' => true, 'lat' => $ll[0], 'lng' => $ll[1]]);
                    }
                }
            } catch (\Throwable) {
                // ตกลงไปแจ้งไม่พบ
            }
        }

        return response()->json(['ok' => false, 'message' => 'อ่านพิกัดจากลิงก์นี้ไม่ได้ ลองกดปักหมุดบนแผนที่แทน'], 422);
    }
}
