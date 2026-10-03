<?php

namespace App\Http\Controllers\Line;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\Shelter;
use App\Models\WaterReport;
use App\Support\Geo;
use App\Support\LineMessenger;
use App\Support\Settings;
use Illuminate\Http\Request;
use Throwable;

/**
 * Webhook ของ LINE OA ประจำจังหวัด
 *  - ส่งตำแหน่ง → ศูนย์พักพิงที่เปิดใกล้สุด 3 แห่ง + ระดับน้ำที่มีคนรายงานรอบตัว
 *  - พิมพ์ "ช่วย" / "น้ำ" / "ศูนย์" / อื่นๆ → ลิงก์ที่ใช่
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, Province $province, LineMessenger $line)
    {
        $body = $request->getContent();
        abort_unless(LineMessenger::verify($province->id, $body, $request->header('X-Line-Signature')), 403);

        foreach ((array) $request->json('events', []) as $ev) {
            $token = $ev['replyToken'] ?? null;
            if (! $token) {
                continue;
            }
            $messages = match (true) {
                ($ev['type'] ?? '') === 'follow' => [$this->welcome($province)],
                ($ev['message']['type'] ?? '') === 'location' => $this->nearby($province, (float) $ev['message']['latitude'], (float) $ev['message']['longitude']),
                ($ev['message']['type'] ?? '') === 'text' => [$this->answer($province, (string) $ev['message']['text'])],
                default => [],
            };
            if ($messages) {
                try {
                    $line->reply($province->id, $token, $messages);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    protected function welcome(Province $province): array
    {
        return LineMessenger::text(implode("\n", [
            'ศูนย์ช่วยเหลือน้ำท่วม'.$province->fullName(),
            '',
            '• ติดอยู่ ต้องการความช่วยเหลือ: '.route('public.help', $province),
            '• แผนที่น้ำ + จุดเสี่ยง: '.route('public.map', $province),
            '• ส่ง "ตำแหน่ง" มาในแชท เพื่อดูศูนย์พักพิงใกล้คุณ',
            '• สายด่วน '.Settings::get('hotline', $province->id),
        ]));
    }

    protected function answer(Province $province, string $text): array
    {
        $t = mb_strtolower($text);

        return match (true) {
            (bool) preg_match('/ช่วย|ติด|อพยพ|help|sos/u', $t) => LineMessenger::text('ขอความช่วยเหลือได้ที่ '.route('public.help', $province)."\nหรือโทร ".Settings::get('hotline', $province->id).' ทันที'),
            (bool) preg_match('/ศูนย์|พักพิง|shelter/u', $t) => LineMessenger::text($this->shelterList($province)),
            (bool) preg_match('/น้ำ|แผนที่|map|ระดับ/u', $t) => LineMessenger::text('แผนที่สถานการณ์น้ำ: '.route('public.map', $province)."\nแจ้งระดับน้ำที่คุณเห็น: ".route('public.reports.create', $province)),
            default => $this->welcome($province),
        };
    }

    protected function shelterList(Province $province, ?array $near = null): string
    {
        $q = Shelter::inProvince($province->id)->where('status', 'open')->where('is_public', true)->get();
        if ($near) {
            $q = $q->sortBy(fn ($s) => Geo::distance($near[0], $near[1], $s->lat, $s->lng));
        }
        $list = $q->take(3)->map(fn ($s) => '🏠 '.$s->name
            .($near ? ' ('.number_format(Geo::distance($near[0], $near[1], $s->lat, $s->lng) / 1000, 1).' กม.)' : '')
            .($s->available() !== null ? ' ว่าง '.$s->available().' ที่' : '')
            ."\n   https://www.google.com/maps/dir/?api=1&destination={$s->lat},{$s->lng}"
            .($s->contact_phone ? "\n   โทร ".$s->contact_phone : ''));

        return $list->isEmpty() ? 'ขณะนี้ยังไม่มีศูนย์พักพิงที่เปิดรับ โทร '.Settings::get('hotline', $province->id) : "ศูนย์พักพิงที่เปิดรับ\n".$list->implode("\n");
    }

    protected function nearby(Province $province, float $lat, float $lng): array
    {
        $reports = WaterReport::inProvince($province->id)->current()
            ->whereBetween('lat', [$lat - 0.02, $lat + 0.02])->whereBetween('lng', [$lng - 0.02, $lng + 0.02])->get()
            ->filter(fn ($r) => Geo::distance($lat, $lng, $r->lat, $r->lng) <= 2000);
        $water = $reports->isEmpty()
            ? 'ยังไม่มีรายงานระดับน้ำในรัศมี 2 กม.'
            : 'มีรายงานน้ำ '.$reports->count().' จุดในรัศมี 2 กม. สูงสุด'.$reports->sortByDesc('level')->first()->levelLabel();

        return [LineMessenger::text($this->shelterList($province, [$lat, $lng])."\n\n".$water."\nแผนที่: ".route('public.map', $province))];
    }
}
