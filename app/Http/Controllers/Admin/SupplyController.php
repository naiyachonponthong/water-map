<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shelter;
use App\Models\ShelterNeed;
use App\Models\SupplyItem;
use App\Models\SupplyMovement;
use App\Support\ReliefOptions;
use App\Support\SupplyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * ของบริจาคและคลัง (คลังกลาง + คลังย่อยที่ศูนย์พักพิง)
 */
class SupplyController extends Controller
{
    public function index(Request $request, SupplyService $service)
    {
        $province = $this->province();
        $items = SupplyItem::inProvince($province->id)
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->query('category')))
            ->orderBy('category')->orderBy('name')->get();
        $shelters = Shelter::inProvince($province->id)->where('status', '!=', 'closed')->orderBy('name')->get(['id', 'name', 'status']);

        return view('admin.supplies.index', [
            'province' => $province,
            'items' => $items,
            'shelters' => $shelters,
            'table' => $service->table($province),
            'low' => $service->low($province),
            'needs' => ShelterNeed::where('status', 'open')->whereHas('shelter', fn ($q) => $q->where('province_id', $province->id))
                ->with('shelter:id,name')->orderByRaw("case priority when 'urgent' then 0 else 1 end")->latest()->limit(30)->get(),
            'movements' => SupplyMovement::where('province_id', $province->id)->with('item:id,name,unit', 'shelter:id,name', 'user:id,name')
                ->latest('id')->limit(25)->get(),
        ]);
    }

    public function storeItem(Request $request)
    {
        $province = $this->province();
        $data = $this->itemData($request, $province->id);
        SupplyItem::create($data + ['province_id' => $province->id, 'is_active' => true]);

        return $this->ok("เพิ่ม {$data['name']} แล้ว");
    }

    public function updateItem(Request $request, SupplyItem $item)
    {
        $this->authorizeProvince($item->province_id);
        $item->update($this->itemData($request, $item->province_id, $item->id) + ['is_active' => $request->boolean('is_active', true)]);

        return $this->ok("บันทึก {$item->name} แล้ว");
    }

    /** รับเข้า / จ่ายออก / โอน / ปรับยอด ในฟอร์มเดียว */
    public function move(Request $request, SupplyService $service)
    {
        $province = $this->province();
        $data = $request->validate([
            'kind' => ['required', Rule::in(['in', 'out', 'transfer', 'adjust'])],
            'supply_item_id' => ['required', Rule::exists('supply_items', 'id')->where('province_id', $province->id)],
            'qty' => ['required', 'integer', 'min:0', 'max:10000000'],
            'from' => ['nullable', 'integer'],
            'to' => ['nullable', 'integer'],
            'donor' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['supply_item_id' => 'รายการ', 'qty' => 'จำนวน']);

        $item = SupplyItem::findOrFail($data['supply_item_id']);
        $loc = fn ($id) => $id ? Shelter::inProvince($province->id)->findOrFail($id) : null;

        try {
            $msg = match ($data['kind']) {
                'in' => tap("รับเข้า {$item->name} {$data['qty']} {$item->unit}", fn () => $service->receive($item, (int) $data['qty'], $loc($data['to'] ?? null), $request->user(), $data['donor'] ?? null, $data['note'] ?? null)),
                'out' => tap("จ่าย {$item->name} {$data['qty']} {$item->unit}", fn () => $service->issue($item, (int) $data['qty'], $loc($data['from'] ?? null), $request->user(), $data['note'] ?? null)),
                'transfer' => tap("โอน {$item->name} {$data['qty']} {$item->unit}", fn () => $service->transfer($item, (int) $data['qty'], $loc($data['from'] ?? null), $loc($data['to'] ?? null), $request->user(), $data['note'] ?? null)),
                'adjust' => tap("ปรับยอด {$item->name} เป็น {$data['qty']} {$item->unit}", fn () => $service->adjust($item, $loc($data['to'] ?? null), (int) $data['qty'], $request->user(), $data['note'] ?? null)),
            };
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $msg);
    }

    protected function itemData(Request $request, int $pid, ?int $ignore = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('supply_items', 'name')->where('province_id', $pid)->ignore($ignore)],
            'category' => ['required', Rule::in(array_keys(ReliefOptions::SUPPLY_CATEGORIES))],
            'unit' => ['required', 'string', 'max:20'],
            'min_stock' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ], [], ['name' => 'ชื่อรายการ', 'unit' => 'หน่วย']);
        $data['min_stock'] = (int) ($data['min_stock'] ?? 0);

        return $data;
    }
}
