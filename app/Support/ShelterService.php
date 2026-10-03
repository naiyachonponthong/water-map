<?php

namespace App\Support;

use App\Models\Evacuee;
use App\Models\HelpRequest;
use App\Models\Province;
use App\Models\RunningNumber;
use App\Models\Shelter;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ศูนย์พักพิงและผู้อพยพ: ลงทะเบียนทั้งครอบครัวในครั้งเดียว ออกจากศูนย์ ย้ายศูนย์ และให้ญาติค้นหา
 */
class ShelterService
{
    /**
     * @param  array<int, array<string, mixed>>  $people  [{name, phone, age_group, gender, needs[], note}]
     * @return Collection<int, Evacuee>
     */
    public function register(Shelter $shelter, array $people, array $common = [], ?User $user = null): Collection
    {
        if ($shelter->status === 'closed') {
            throw new RuntimeException('ศูนย์นี้ปิดแล้ว');
        }

        $created = DB::transaction(function () use ($shelter, $people, $common, $user) {
            $province = Province::find($shelter->province_id);
            $family = count($people) > 1 ? $this->code($province, 'FM') : null;
            $out = collect();
            foreach ($people as $p) {
                if (blank($p['name'] ?? null)) {
                    continue;
                }
                $phone = filled($p['phone'] ?? null) ? User::normalizePhone($p['phone']) : (filled($common['phone'] ?? null) ? User::normalizePhone($common['phone']) : null);
                $out->push(Evacuee::create([
                    'province_id' => $shelter->province_id,
                    'shelter_id' => $shelter->id,
                    'code' => $this->code($province, 'EV'),
                    'family_code' => $family,
                    'name' => trim($p['name']),
                    'phone' => $phone,
                    'phone_hash' => $phone ? HelpRequestService::phoneHash($phone) : null,
                    'age_group' => $p['age_group'] ?? null,
                    'gender' => $p['gender'] ?? null,
                    'needs' => array_values($p['needs'] ?? []) ?: null,
                    'address' => $common['address'] ?? null,
                    'note' => $p['note'] ?? null,
                    'allow_lookup' => (bool) ($common['allow_lookup'] ?? true),
                    'status' => 'in',
                    'help_request_id' => $common['help_request_id'] ?? null,
                    'vulnerable_household_id' => $common['vulnerable_household_id'] ?? null,
                    'registered_by' => $user?->id,
                    'checked_in_at' => now(),
                ]));
            }

            return $out;
        });

        $shelter->recount();
        Live::signal($shelter->province_id, 'shelter.changed', ['id' => $shelter->id, 'occupancy' => $shelter->occupancy]);

        return $created;
    }

    public function checkOut(Evacuee $e, string $reason, ?User $user = null, bool $family = false): int
    {
        $list = $family && $e->family_code
            ? Evacuee::where('family_code', $e->family_code)->where('status', 'in')->get()
            : collect([$e]);
        foreach ($list as $x) {
            $x->update(['status' => 'out', 'checked_out_at' => now(), 'out_reason' => $reason]);
        }
        $list->pluck('shelter_id')->unique()->each(fn ($id) => Shelter::find($id)?->recount());

        return $list->count();
    }

    public function transfer(Evacuee $e, Shelter $to, ?User $user = null, bool $family = false): int
    {
        if ($to->id === $e->shelter_id) {
            throw new RuntimeException('อยู่ศูนย์นี้อยู่แล้ว');
        }
        if ($to->status === 'closed') {
            throw new RuntimeException('ศูนย์ปลายทางปิดแล้ว');
        }
        $from = $e->shelter;
        $list = $family && $e->family_code
            ? Evacuee::where('family_code', $e->family_code)->where('status', 'in')->get()
            : collect([$e]);
        foreach ($list as $x) {
            $x->update(['shelter_id' => $to->id, 'note' => trim(($x->note ? $x->note."\n" : '').'ย้ายจาก'.$from?->name.' '.now()->format('d/m H:i'))]);
        }
        $from?->recount();
        $to->recount();

        return $list->count();
    }

    /**
     * ญาติค้นหาด้วยเบอร์โทร (ต้องตรงทั้งเบอร์ และผู้อพยพยินยอม)
     *
     * @return Collection<int, array{name: string, shelter: string, address: ?string, contact: ?string, since: string}>
     */
    public function lookup(Province $province, string $phone): Collection
    {
        $phone = User::normalizePhone($phone);
        if (strlen($phone) < 9) {
            return collect();
        }

        return Evacuee::inProvince($province->id)->where('phone_hash', HelpRequestService::phoneHash($phone))
            ->where('status', 'in')->where('allow_lookup', true)->with('shelter')->get()
            ->map(fn (Evacuee $e) => [
                'name' => $e->maskedName(),
                'shelter' => $e->shelter->name,
                'address' => $e->shelter->address,
                'contact' => $e->shelter->contact_phone,
                'since' => ThaiDate::compact($e->checked_in_at),
                'lat' => $e->shelter->lat,
                'lng' => $e->shelter->lng,
            ]);
    }

    /** ข้อมูลตั้งต้นจากเคสที่ทีมส่งเข้าศูนย์ */
    public function fromCase(HelpRequest $case): array
    {
        return [
            'people' => [['name' => $case->requester_name, 'phone' => $case->requester_phone]],
            'address' => trim(($case->address_text ?? '').' '.$case->areaLabel()),
            'help_request_id' => $case->id,
            'vulnerable_household_id' => $case->household_id,
            'people_count' => (int) ($case->people_rescued ?: $case->people_count),
        ];
    }

    protected function code(Province $province, string $prefix): string
    {
        $yymm = substr((string) (now()->year + 543), -2).now()->format('m');

        return sprintf('%s%s-%s-%04d', $prefix, $province->code, $yymm, RunningNumber::next(strtolower($prefix).":{$province->code}:$yymm"));
    }
}
