<?php

namespace App\Support;

use App\Models\Province;
use App\Models\Shelter;
use App\Models\SupplyItem;
use App\Models\SupplyMovement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * คลังของบริจาค: คลังกลาง (shelter_id = null) + คลังย่อยที่แต่ละศูนย์พักพิง
 * ยอดคงเหลือ = ผลรวม qty ของรายการเคลื่อนไหว (ไม่เก็บยอดแยก จึงตรวจย้อนหลังได้เสมอ)
 */
class SupplyService
{
    public function stock(int $itemId, ?int $shelterId): int
    {
        return (int) SupplyMovement::where('supply_item_id', $itemId)
            ->when($shelterId, fn ($q) => $q->where('shelter_id', $shelterId), fn ($q) => $q->whereNull('shelter_id'))
            ->sum('qty');
    }

    /** ตารางคงเหลือ: [item_id => [shelter_id|0 => qty]] */
    public function table(Province $province): Collection
    {
        return SupplyMovement::where('province_id', $province->id)
            ->selectRaw('supply_item_id, coalesce(shelter_id, 0) as loc, sum(qty) as total')
            ->groupBy('supply_item_id', DB::raw('coalesce(shelter_id, 0)'))
            ->get()
            ->groupBy('supply_item_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($r) => [(int) $r->loc => (int) $r->total]));
    }

    public function receive(SupplyItem $item, int $qty, ?Shelter $at, ?User $user, ?string $donor = null, ?string $note = null): SupplyMovement
    {
        $this->positive($qty);

        return $this->move($item, $at, $qty, 'in', $user, ['donor' => $donor, 'note' => $note]);
    }

    public function issue(SupplyItem $item, int $qty, ?Shelter $from, ?User $user, ?string $note = null, ?int $caseId = null): SupplyMovement
    {
        $this->positive($qty);

        return DB::transaction(function () use ($item, $qty, $from, $user, $note, $caseId) {
            $this->lock($item);
            $have = $this->stock($item->id, $from?->id);
            if ($have < $qty) {
                throw new RuntimeException("{$item->name} เหลือ {$have} {$item->unit} ไม่พอจ่าย {$qty}");
            }

            return $this->move($item, $from, -$qty, 'out', $user, ['note' => $note, 'help_request_id' => $caseId]);
        });
    }

    public function transfer(SupplyItem $item, int $qty, ?Shelter $from, ?Shelter $to, ?User $user, ?string $note = null): string
    {
        $this->positive($qty);
        if ($from?->id === $to?->id) {
            throw new RuntimeException('ต้นทางและปลายทางเป็นที่เดียวกัน');
        }

        return DB::transaction(function () use ($item, $qty, $from, $to, $user, $note) {
            $this->lock($item);
            $have = $this->stock($item->id, $from?->id);
            if ($have < $qty) {
                throw new RuntimeException("{$item->name} ที่".($from?->name ?? 'คลังกลาง')." เหลือ {$have} {$item->unit}");
            }
            $ref = 'TR'.Str::upper(Str::random(8));
            $this->move($item, $from, -$qty, 'transfer_out', $user, ['ref' => $ref, 'note' => $note ?? 'โอนไป'.($to?->name ?? 'คลังกลาง')]);
            $this->move($item, $to, $qty, 'transfer_in', $user, ['ref' => $ref, 'note' => $note ?? 'รับจาก'.($from?->name ?? 'คลังกลาง')]);

            return $ref;
        });
    }

    /** นับจริงแล้วปรับยอดให้ตรง */
    public function adjust(SupplyItem $item, ?Shelter $at, int $actual, ?User $user, ?string $note = null): ?SupplyMovement
    {
        if ($actual < 0) {
            throw new RuntimeException('จำนวนต้องไม่ติดลบ');
        }

        return DB::transaction(function () use ($item, $at, $actual, $user, $note) {
            $this->lock($item);
            $diff = $actual - $this->stock($item->id, $at?->id);

            return $diff === 0 ? null : $this->move($item, $at, $diff, 'adjust', $user, ['note' => $note ?? 'ตรวจนับ']);
        });
    }

    /** รายการที่ต่ำกว่าขั้นต่ำ (คลังกลาง) */
    public function low(Province $province): Collection
    {
        $table = $this->table($province);

        return SupplyItem::inProvince($province->id)->where('is_active', true)->where('min_stock', '>', 0)->get()
            ->filter(fn ($i) => ($table[$i->id][0] ?? 0) < $i->min_stock)
            ->map(fn ($i) => ['item' => $i, 'qty' => $table[$i->id][0] ?? 0])->values();
    }

    protected function move(SupplyItem $item, ?Shelter $at, int $qty, string $kind, ?User $user, array $extra = []): SupplyMovement
    {
        if ($at && $at->province_id !== $item->province_id) {
            throw new RuntimeException('ศูนย์คนละจังหวัด');
        }

        return SupplyMovement::create([
            'province_id' => $item->province_id, 'supply_item_id' => $item->id, 'shelter_id' => $at?->id,
            'qty' => $qty, 'kind' => $kind, 'user_id' => $user?->id, 'created_at' => now(),
        ] + array_filter($extra, fn ($v) => $v !== null));
    }

    /** ล็อกแถวสินค้า กันจ่ายพร้อมกันจนติดลบ */
    protected function lock(SupplyItem $item): void
    {
        SupplyItem::whereKey($item->id)->lockForUpdate()->first();
    }

    protected function positive(int $qty): void
    {
        if ($qty <= 0) {
            throw new RuntimeException('จำนวนต้องมากกว่า 0');
        }
    }
}
