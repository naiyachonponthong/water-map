<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\District;
use App\Models\HelpRequest;
use App\Models\Province;
use App\Models\RiskPoint;
use App\Models\Shelter;
use App\Models\SupplyItem;
use App\Models\Team;
use App\Models\User;
use App\Models\VulnerableHousehold;
use App\Models\WaterStation;
use App\Support\AlertService;
use App\Support\DispatchService;
use App\Support\HelpRequestService;
use App\Support\RecoveryService;
use App\Support\RiskEngine;
use App\Support\ShelterService;
use App\Support\StationService;
use App\Support\SupplyService;
use App\Support\WaterReportService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * ข้อมูลตัวอย่างครบทุกเฟส สำหรับไล่ทดสอบหน้าจอ ฝึกเจ้าหน้าที่ และทดสอบโหลดบนเครื่องทดสอบ
 *   php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder
 * ใช้จังหวัด DEMO_PROVINCE (ค่าเริ่มต้น chachoengsao) ห้ามรันบน production
 * บัญชีตัวอย่างทุกบัญชีใช้รหัสผ่าน demo1234
 */
class DemoSeeder extends Seeder
{
    protected Province $p;

    protected array $users = [];

    protected array $teams = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder ห้ามรันบน production');
        }
        mt_srand(2569);

        $this->p = Province::where('slug', env('DEMO_PROVINCE', 'chachoengsao'))->firstOrFail();
        if (! $this->p->center_lat) {
            $this->p->update(['center_lat' => 13.69, 'center_lng' => 101.07, 'default_zoom' => 10]);
        }
        $this->p->update(['is_active' => true, 'command_open' => true, 'web_help_open' => true, 'command_opened_at' => now()->subDays(3)]);

        $this->users();
        $this->teams();
        $this->cases();
        $this->risks();
        $this->stations();
        $this->reports();
        $this->shelters();
        $this->supplies();
        $this->comms();
        $this->recovery();

        app(RiskEngine::class)->evaluate($this->p);
        $this->command?->info('สร้างข้อมูลตัวอย่างแล้ว เข้าสู่ระบบด้วยบัญชี 0990000001 ถึง 0990000007 รหัสผ่าน demo1234');
    }

    /** จุดสุ่มรอบกลางจังหวัด (ประมาณ ±15 กม.) */
    protected function spot(float $spread = 0.13): array
    {
        return [
            round($this->p->center_lat + (mt_rand(-1000, 1000) / 1000) * $spread, 6),
            round($this->p->center_lng + (mt_rand(-1000, 1000) / 1000) * $spread, 6),
        ];
    }

    protected function users(): void
    {
        $list = [
            ['ผอ.ศูนย์ ตัวอย่าง', 'province-admin'], ['นายสั่งการ ตัวอย่าง', 'dispatcher'], ['นางคัดกรอง ตัวอย่าง', 'moderator'],
            ['หัวหน้าทีมเรือ 1', 'team-leader'], ['หัวหน้าทีมเรือ 2', 'team-leader'], ['หัวหน้าทีมรถยกสูง', 'team-leader'], ['เจ้าหน้าที่ศูนย์พักพิง', 'shelter-staff'],
        ];
        foreach ($list as $i => [$name, $role]) {
            $u = User::firstOrCreate(['phone' => sprintf('09900000%02d', $i + 1)], [
                'name' => $name, 'password' => 'demo1234', 'province_id' => $this->p->id, 'status' => 'active',
            ]);
            $u->syncRoles([$role]);
            $this->users[$role][] = $u;
        }
    }

    protected function teams(): void
    {
        $defs = [['ทีมเรือกู้ภัย 1', 'foundation', 'boat'], ['ทีมเรือกู้ภัย 2', 'volunteer', 'boat'], ['ทีมรถยกสูง อบจ.', 'local_gov', 'high_truck']];
        foreach ($defs as $i => [$name, $type, $vehicle]) {
            [$lat, $lng] = $this->spot(0.05);
            $team = Team::firstOrCreate(['province_id' => $this->p->id, 'name' => $name], [
                'type' => $type, 'base_lat' => $lat, 'base_lng' => $lng, 'last_lat' => $lat, 'last_lng' => $lng, 'last_seen_at' => now(),
                'status' => 'available', 'is_active' => true, 'phone' => sprintf('08100000%02d', $i + 1),
            ]);
            $leader = $this->users['team-leader'][$i];
            if (! $team->members()->where('users.id', $leader->id)->exists()) {
                $team->members()->attach($leader->id, ['role_in_team' => 'leader']);
            }
            $team->update(['leader_id' => $leader->id]);
            $team->vehicles()->firstOrCreate(['name' => $name.' คันที่ 1'], ['type' => $vehicle, 'capacity' => $vehicle === 'boat' ? 8 : 15, 'status' => 'ready']);
            $this->teams[] = $team;
        }
    }

    protected function cases(): void
    {
        $svc = app(HelpRequestService::class);
        $dispatch = app(DispatchService::class);
        $staff = $this->users['dispatcher'][0];
        $names = ['สมชาย', 'สมหญิง', 'ประยูร', 'บุญมี', 'มาลี', 'สุดา', 'วิชัย', 'อำนวย', 'จันทร์เพ็ญ', 'ทองดี', 'สายสุนีย์', 'ประเสริฐ'];
        $vul = [[], [], ['elderly'], ['bedridden'], ['infant'], ['pregnant'], [], ['disabled', 'elderly'], ['pets'], []];
        $needs = [['evacuate'], ['food'], ['evacuate', 'medicine'], ['food', 'power'], ['medicine']];

        for ($i = 0; $i < 36; $i++) {
            [$lat, $lng] = $this->spot();
            [$case] = $svc->submit($this->p, [
                'lat' => $lat, 'lng' => $lng, 'water_level' => mt_rand(2, 6), 'people_count' => mt_rand(1, 8),
                'vulnerable' => $vul[$i % count($vul)], 'needs' => $needs[$i % count($needs)],
                'needs_note' => $i % 4 === 0 ? 'น้ำขึ้นเร็ว ออกจากบ้านไม่ได้' : null,
                'requester_name' => $names[$i % count($names)].' ตัวอย่าง', 'requester_phone' => sprintf('08200%05d', $i + 1),
                'address_text' => mt_rand(1, 200).' หมู่ '.mt_rand(1, 12),
            ], ['source' => $i % 5 === 0 ? 'phone' : 'web', 'user' => $i % 5 === 0 ? $staff : null]);

            $created = now()->subMinutes(mt_rand(20, 60 * 48));
            $case->forceFill(['created_at' => $created])->saveQuietly();

            // กระจายสถานะ: 1/6 รอคัดกรอง, ที่เหลือเข้าคิว แล้วบางส่วนมีทีมรับ / ช่วยเสร็จ
            if ($i % 6 === 0) {
                continue;
            }
            $svc->changeStatus($case, 'queued', $staff);
            if ($i % 6 === 1) {
                continue;
            }
            $team = $this->teams[$i % count($this->teams)];
            $leader = $team->leader ?? $this->users['team-leader'][0];
            $a = $dispatch->offer($case->fresh(), $team, $staff);
            $dispatch->respond($a, true, $leader);
            if ($i % 6 === 2) {
                continue;
            }
            $dispatch->progress($a->fresh(), 'en_route', $leader);
            $dispatch->progress($a->fresh(), 'on_site', $leader);
            if ($i % 6 === 3) {
                continue;
            }
            $dispatch->complete($a->fresh(), $leader, $i % 3 === 0 ? 'shelter' : 'stay', $case->people_count);

            // ให้เวลาในรายงานดูสมจริง: รับงาน 3-20 นาที ถึง 15-90 นาที หลังแจ้ง
            $acc = $created->copy()->addMinutes(mt_rand(3, 20));
            $arr = $acc->copy()->addMinutes(mt_rand(12, 70));
            Assignment::whereKey($a->id)->update(['offered_at' => $created->copy()->addMinutes(2), 'responded_at' => $acc, 'en_route_at' => $acc, 'arrived_at' => $arr, 'done_at' => $arr->copy()->addMinutes(mt_rand(10, 40))]);
            HelpRequest::whereKey($case->id)->update(['closed_at' => $arr->copy()->addMinutes(45)]);
        }
        $this->teams[2]->update(['status' => 'available']);
    }

    protected function risks(): void
    {
        $defs = [['สะพานข้ามคลองท่าไข่', 'road', 'high', 3], ['ชุมชนริมน้ำบางปะกง', 'flood_prone', 'high', 2], ['หม้อแปลงไฟฟ้าตลาดเก่า', 'hazard', 'medium', 3], ['ถนนเลียบคลองสาย 2', 'road', 'medium', 2], ['โรงพยาบาลส่งเสริมสุขภาพตำบล', 'facility', 'low', 4]];
        foreach ($defs as [$name, $type, $sev, $trig]) {
            [$lat, $lng] = $this->spot(0.08);
            RiskPoint::firstOrCreate(['province_id' => $this->p->id, 'name' => $name], [
                'type' => $type, 'lat' => $lat, 'lng' => $lng, 'radius_m' => 800, 'trigger_level' => $trig, 'severity' => $sev,
                'is_public' => true, 'status' => 'normal', 'review' => 'approved', 'source' => 'staff',
            ]);
        }
        [$lat, $lng] = $this->spot();
        RiskPoint::firstOrCreate(['province_id' => $this->p->id, 'name' => 'ทางลอดใต้สะพาน (ประชาชนเสนอ)'], [
            'type' => 'road', 'lat' => $lat, 'lng' => $lng, 'trigger_level' => 2, 'severity' => 'medium', 'is_public' => true, 'status' => 'normal',
            'review' => 'pending', 'source' => 'citizen', 'proposer_name' => 'ชาวบ้าน ตัวอย่าง',
        ]);

        foreach ([['ยายบุญมา ตัวอย่าง', ['bedridden', 'elderly_alone']], ['ตาสมาน ตัวอย่าง', ['oxygen']], ['นางสาวพร ตัวอย่าง', ['wheelchair']], ['ด.ช.ต้น ตัวอย่าง', ['infant']]] as $i => [$name, $cond]) {
            [$lat, $lng] = $this->spot(0.06);
            VulnerableHousehold::firstOrCreate(['province_id' => $this->p->id, 'head_name' => $name], [
                'lat' => $lat, 'lng' => $lng, 'members' => mt_rand(1, 4), 'conditions' => $cond, 'phone' => sprintf('08300000%02d', $i + 1),
                'caretaker_name' => 'อสม. ตัวอย่าง', 'caretaker_phone' => '0839999999', 'consent_at' => now(), 'is_active' => true,
            ]);
        }
    }

    protected function stations(): void
    {
        $svc = app(StationService::class);
        foreach ([['สะพานแม่น้ำบางปะกง', 'บางปะกง', 2.8, 0.004], ['ปตร.ท่าไข่', 'คลองท่าไข่', 1.9, 0.012], ['หลักวัดน้ำ อบต.', 'คลองสาย 2', 1.2, -0.003]] as [$name, $river, $base, $rise]) {
            [$lat, $lng] = $this->spot(0.07);
            $s = WaterStation::firstOrCreate(['province_id' => $this->p->id, 'name' => $name], [
                'river' => $river, 'lat' => $lat, 'lng' => $lng, 'unit' => 'ม.รทก.', 'bank_level' => 3.2, 'watch_level' => 2.6,
                'warning_level' => 2.9, 'critical_level' => 3.2, 'influence_radius_m' => 3000, 'fetch_mode' => 'manual', 'is_public' => true, 'is_active' => true,
            ]);
            for ($h = 72; $h >= 0; $h -= 2) {
                $v = $base + (72 - $h) * $rise + sin($h / 4) * 0.05;
                $svc->record($s, round($v, 2), now()->subHours($h));
            }
        }
    }

    protected function reports(): void
    {
        $svc = app(WaterReportService::class);
        for ($i = 0; $i < 40; $i++) {
            [$lat, $lng] = $this->spot();
            $svc->submit($this->p, [
                'lat' => $lat, 'lng' => $lng, 'level' => mt_rand(1, 5), 'trend' => ['rising', 'steady', 'falling'][$i % 3],
                'place_type' => ['road', 'house', 'field', 'canal'][$i % 4], 'note' => $i % 5 === 0 ? 'รถเล็กผ่านไม่ได้' : null,
            ], ['source' => 'web', 'device_hash' => hash('sha256', 'demo-'.$i)]);
        }
    }

    protected function shelters(): void
    {
        $svc = app(ShelterService::class);
        $staff = $this->users['shelter-staff'][0];
        foreach ([['วัดตัวอย่างวราราม', 'temple', 300], ['โรงเรียนบ้านตัวอย่าง', 'school', 150], ['หอประชุม อบจ.', 'hall', 500]] as $i => [$name, $type, $cap]) {
            [$lat, $lng] = $this->spot(0.05);
            $s = Shelter::firstOrCreate(['province_id' => $this->p->id, 'name' => $name], [
                'type' => $type, 'lat' => $lat, 'lng' => $lng, 'capacity' => $cap, 'status' => $i === 2 ? 'preparing' : 'open',
                'facilities' => ['toilet', 'power', 'kitchen', 'medical'], 'contact_name' => 'ผู้ประสานงาน', 'contact_phone' => '03800000'.$i, 'is_public' => true, 'opened_at' => now()->subDays(2),
            ]);
            $s->staff()->syncWithoutDetaching([$staff->id]);
            if ($s->status !== 'open') {
                continue;
            }
            for ($f = 0; $f < 12; $f++) {
                $people = collect(range(1, mt_rand(1, 5)))->map(fn ($k) => [
                    'name' => 'ผู้อพยพ '.($i * 100 + $f).'-'.$k, 'phone' => $k === 1 ? sprintf('0840%02d%04d', $i, $f) : null,
                    'age_group' => ['adult', 'child', 'elderly', 'infant'][mt_rand(0, 3)], 'needs' => mt_rand(0, 5) === 0 ? ['medical'] : [],
                ])->all();
                $svc->register($s, $people, ['allow_lookup' => true, 'address' => 'หมู่ '.mt_rand(1, 12)], $staff);
            }
            $s->needs()->firstOrCreate(['item' => 'ผ้าอ้อมผู้ใหญ่ ไซซ์ L'], ['qty' => 200, 'unit' => 'ชิ้น', 'priority' => 'urgent', 'status' => 'open']);
            $s->needs()->firstOrCreate(['item' => 'นมผงเด็ก'], ['qty' => 30, 'unit' => 'กระป๋อง', 'priority' => 'normal', 'status' => 'open']);
        }
    }

    protected function supplies(): void
    {
        $svc = app(SupplyService::class);
        $admin = $this->users['province-admin'][0];
        $shelter = Shelter::inProvince($this->p->id)->where('status', 'open')->first();
        foreach ([['น้ำดื่ม 600 มล.', 'water', 'แพ็ค', 200, 1500], ['ข้าวสาร 5 กก.', 'food', 'ถุง', 50, 300], ['ถุงยังชีพ', 'food', 'ชุด', 100, 400], ['ยาสามัญประจำบ้าน', 'medicine', 'ชุด', 30, 60], ['ผ้าอ้อมผู้ใหญ่', 'hygiene', 'ห่อ', 40, 20]] as [$name, $cat, $unit, $min, $in]) {
            $item = SupplyItem::firstOrCreate(['province_id' => $this->p->id, 'name' => $name], ['category' => $cat, 'unit' => $unit, 'min_stock' => $min, 'is_active' => true]);
            $svc->receive($item, $in, null, $admin, 'มูลนิธิตัวอย่าง');
            if ($shelter && $in > 50) {
                $svc->transfer($item, intdiv($in, 4), null, $shelter, $admin);
                $svc->issue($item, intdiv($in, 10), $shelter, $admin, 'แจกผู้อพยพ');
            }
        }
    }

    protected function comms(): void
    {
        $alerts = app(AlertService::class);
        $admin = $this->users['province-admin'][0];
        $alerts->raise($this->p->id, 'manual:demo', 'manual', 'warning', 'เขื่อนเพิ่มการระบายน้ำ ประชาชนริมแม่น้ำเตรียมขนของขึ้นที่สูง', 'คาดว่าระดับน้ำจะสูงขึ้น 30 ถึง 50 ซม. ภายใน 24 ชั่วโมง', ['created_by' => $admin->id, 'is_public' => true]);
        Announcement::firstOrCreate(['province_id' => $this->p->id, 'title' => 'เปิดศูนย์พักพิงวัดตัวอย่างวราราม'], [
            'body' => "รับผู้อพยพได้ 300 คน มีอาหาร ห้องน้ำ และจุดปฐมพยาบาล\nติดต่อผู้ประสานงานที่ศูนย์", 'level' => 'info', 'audience' => 'public',
            'pinned' => true, 'published_at' => now()->subHours(6), 'created_by' => $admin->id,
        ]);
    }

    protected function recovery(): void
    {
        $svc = app(RecoveryService::class);
        $done = HelpRequest::inProvince($this->p->id)->where('status', 'rescued')->limit(4)->get();
        foreach ($done as $i => $c) {
            $svc->submit($this->p, [
                'head_name' => $c->requester_name, 'phone' => $c->requester_phone, 'address' => $c->address_text ?: 'หมู่ 1',
                'lat' => $c->lat, 'lng' => $c->lng, 'members' => $c->people_count, 'tenure' => 'own',
                'house_damage' => ['minor', 'major', 'destroyed', 'major'][$i], 'water_level' => $c->water_level, 'flood_days' => mt_rand(3, 14),
                'losses' => [['furniture'], ['appliances', 'vehicle'], ['furniture', 'appliances', 'tools'], ['crops']][$i],
                'crop_rai' => $i === 3 ? 12 : null,
            ], ['source' => 'staff', 'user' => $this->users['province-admin'][0]]);
        }
    }
}
