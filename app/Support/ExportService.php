<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\Evacuee;
use App\Models\HelpRequest;
use App\Models\Province;
use App\Models\StationReading;
use App\Models\SupplyMovement;
use App\Models\WaterReport;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ส่งออก CSV (UTF-8 BOM เปิดใน Excel ภาษาไทยได้ทันที) แบบ stream ทีละชุด ไม่กินหน่วยความจำ
 * เบอร์โทรถูกปิดบังเสมอ ยกเว้นผู้มีสิทธิ์เลือก "รวมเบอร์โทร"
 */
class ExportService
{
    public const DATASETS = [
        'cases' => ['เคสขอความช่วยเหลือ', 'cases.view'],
        'assignments' => ['งานของทีมกู้ภัย', 'cases.view'],
        'evacuees' => ['ผู้อพยพ', 'evacuees.manage'],
        'supplies' => ['รับ-จ่ายของบริจาค', 'supplies.manage'],
        'reports' => ['รายงานระดับน้ำ', 'reports.moderate'],
        'readings' => ['ค่าระดับน้ำที่สถานี', 'stations.manage'],
        'claims' => ['คำร้องเยียวยาหลังน้ำลด', 'recovery.manage'],
    ];

    /** สิทธิ์ที่ต้องมีเพิ่มเพื่อส่งออกเบอร์เต็ม */
    public const PHONE_PERMISSION = [
        'cases' => 'cases.manage',
        'evacuees' => 'evacuees.manage',
        'reports' => 'reports.moderate',
        'claims' => 'recovery.manage',
    ];

    public function stream(string $dataset, Province $province, Carbon $from, Carbon $to, bool $phones = false): StreamedResponse
    {
        $name = $dataset.'-'.$province->slug.'-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($dataset, $province, $from, $to, $phones) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $this->{$dataset}($out, $province, $from, $to, $phones);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    protected function phone(?string $p, bool $full): string
    {
        if (! $p) {
            return '';
        }

        return $full ? $p : '0xx-xxx-'.substr($p, -4);
    }

    protected function dt($d): string
    {
        return $d ? Carbon::parse($d)->timezone(config('app.timezone'))->format('Y-m-d H:i') : '';
    }

    /** กันสูตร Excel ฝังมาในข้อความที่ผู้ใช้กรอก (CSV injection) */
    protected function cell($v): string
    {
        $v = (string) ($v ?? '');

        return preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
    }

    protected function row($out, array $cols): void
    {
        fputcsv($out, array_map(fn ($c) => is_numeric($c) ? $c : $this->cell($c), $cols));
    }

    protected function cases($out, Province $p, Carbon $from, Carbon $to, bool $phones): void
    {
        $this->row($out, ['เลขเคส', 'วันเวลาแจ้ง', 'ช่องทาง', 'สถานะ', 'ความเร่งด่วน', 'คะแนน', 'อำเภอ', 'ตำบล', 'ละติจูด', 'ลองจิจูด', 'ระดับน้ำ', 'จำนวนคน', 'กลุ่มเปราะบาง', 'สิ่งที่ต้องการ', 'ผู้แจ้ง', 'เบอร์', 'ทีม', 'ผลการช่วยเหลือ', 'ช่วยออกมา', 'ปิดเคส']);
        HelpRequest::inProvince($p->id)->whereBetween('created_at', [$from, $to])
            ->with('district:id,name_th', 'subdistrict:id,name_th', 'team:id,name')
            ->orderBy('id')->chunk(500, function ($rows) use ($out, $phones) {
                foreach ($rows as $c) {
                    $this->row($out, [
                        $c->code, $this->dt($c->created_at), CaseOptions::SOURCES[$c->source][0] ?? $c->source, $c->statusLabel(), $c->priorityLabel(), $c->priority_score,
                        $c->district?->name_th, $c->subdistrict?->name_th, $c->lat, $c->lng, CaseOptions::waterLevel((int) $c->water_level), $c->people_count,
                        implode(', ', $c->vulnerableLabels()), implode(', ', $c->needLabels()), $c->requester_name, $this->phone($c->requester_phone, $phones),
                        $c->team?->name, $c->outcome ? (CaseOptions::OUTCOMES[$c->outcome] ?? $c->outcome) : '', $c->people_rescued, $this->dt($c->closed_at),
                    ]);
                }
            });
    }

    protected function assignments($out, Province $p, Carbon $from, Carbon $to, bool $phones): void
    {
        $this->row($out, ['เลขเคส', 'ทีม', 'สถานะ', 'ช่องทาง', 'เสนองาน', 'ตอบรับ', 'ออกเดินทาง', 'ถึงที่เกิดเหตุ', 'เสร็จ', 'นาทีจนถึง', 'ช่วยออกมา', 'เหตุผลปฏิเสธ']);
        Assignment::whereHas('helpRequest', fn ($q) => $q->where('province_id', $p->id))
            ->whereBetween('created_at', [$from, $to])->with('helpRequest:id,code,created_at', 'team:id,name')
            ->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $a) {
                    $this->row($out, [
                        $a->helpRequest?->code, $a->team?->name, $a->statusLabel(), $a->via, $this->dt($a->offered_at), $this->dt($a->responded_at),
                        $this->dt($a->en_route_at), $this->dt($a->arrived_at), $this->dt($a->done_at),
                        $a->arrived_at && $a->helpRequest ? $a->helpRequest->created_at->diffInMinutes($a->arrived_at, true) : '',
                        $a->people_rescued, $a->decline_reason,
                    ]);
                }
            });
    }

    protected function evacuees($out, Province $p, Carbon $from, Carbon $to, bool $phones): void
    {
        $this->row($out, ['รหัส', 'รหัสครอบครัว', 'ชื่อ', 'เบอร์', 'ช่วงอายุ', 'เพศ', 'ภาวะ', 'ศูนย์', 'สถานะ', 'เข้า', 'ออก', 'เหตุผลออก', 'ที่อยู่เดิม']);
        Evacuee::inProvince($p->id)->where('checked_in_at', '<=', $to)
            ->where(fn ($q) => $q->whereNull('checked_out_at')->orWhere('checked_out_at', '>=', $from))
            ->with('shelter:id,name')->orderBy('id')->chunk(500, function ($rows) use ($out, $phones) {
                foreach ($rows as $e) {
                    $this->row($out, [
                        $e->code, $e->family_code, $e->name, $this->phone($e->phone, $phones), $e->ageLabel(), ReliefOptions::GENDERS[$e->gender] ?? '',
                        implode(', ', $e->needLabels()), $e->shelter?->name, $e->status === 'in' ? 'อยู่ในศูนย์' : 'ออกแล้ว',
                        $this->dt($e->checked_in_at), $this->dt($e->checked_out_at), $e->out_reason, $e->address,
                    ]);
                }
            });
    }

    protected function supplies($out, Province $p, Carbon $from, Carbon $to, bool $phones): void
    {
        $this->row($out, ['วันเวลา', 'รายการ', 'หน่วย', 'ประเภท', 'จำนวน', 'คลัง', 'ผู้บริจาค', 'อ้างอิง', 'หมายเหตุ', 'ผู้บันทึก']);
        SupplyMovement::where('province_id', $p->id)->whereBetween('created_at', [$from, $to])
            ->with('item:id,name,unit', 'shelter:id,name', 'user:id,name')->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $m) {
                    $this->row($out, [$this->dt($m->created_at), $m->item?->name, $m->item?->unit, $m->kindLabel(), $m->qty, $m->shelter?->name ?? 'คลังกลาง', $m->donor, $m->ref, $m->note, $m->user?->name]);
                }
            });
    }

    protected function reports($out, Province $p, Carbon $from, Carbon $to, bool $phones): void
    {
        $this->row($out, ['เลขที่', 'วันเวลา', 'ช่องทาง', 'สถานะ', 'ยืนยันแล้ว', 'ระดับน้ำ', 'แนวโน้ม', 'อำเภอ', 'ตำบล', 'ละติจูด', 'ลองจิจูด', 'คนยืนยัน', 'แจ้งน้ำลด', 'แจ้งไม่ถูกต้อง', 'รายละเอียด', 'ผู้แจ้ง', 'เบอร์']);
        WaterReport::inProvince($p->id)->whereBetween('created_at', [$from, $to])
            ->with('district:id,name_th', 'subdistrict:id,name_th')->orderBy('id')->chunk(500, function ($rows) use ($out, $phones) {
                foreach ($rows as $r) {
                    $this->row($out, [
                        $r->id, $this->dt($r->created_at), $r->sourceLabel(), $r->statusLabel(), $r->verified ? 'ใช่' : '', $r->levelLabel(), $r->trendLabel(),
                        $r->district?->name_th, $r->subdistrict?->name_th, $r->lat, $r->lng, $r->confirm_count, $r->recede_count, $r->wrong_count,
                        $r->note, $r->reporter_name, $this->phone($r->reporter_phone, $phones),
                    ]);
                }
            });
    }

    protected function readings($out, Province $p, Carbon $from, Carbon $to, bool $phones): void
    {
        $this->row($out, ['สถานี', 'รหัส', 'ลำน้ำ', 'เวลา', 'ระดับน้ำ', 'หน่วย', 'ที่มา']);
        StationReading::whereHas('station', fn ($q) => $q->where('province_id', $p->id))
            ->whereBetween('measured_at', [$from, $to])->with('station:id,name,code,river,unit')
            ->orderBy('water_station_id')->orderBy('measured_at')->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    $this->row($out, [$r->station?->name, $r->station?->code, $r->station?->river, $this->dt($r->measured_at), $r->value, $r->station?->unit, $r->source]);
                }
            });
    }

    protected function claims($out, Province $p, Carbon $from, Carbon $to, bool $phones): void
    {
        $this->row($out, ['เลขคำร้อง', 'วันที่ยื่น', 'ชื่อ', 'เบอร์', 'ที่อยู่', 'อำเภอ', 'ตำบล', 'สมาชิก', 'ความเสียหายที่แจ้ง', 'ความเสียหายที่สำรวจ', 'ทรัพย์สิน', 'พืชผล (ไร่)', 'คะแนนหลักฐาน', 'สถานะ', 'ยอดแนะนำ', 'ยอดอนุมัติ', 'ยอดจ่าย', 'เลขอ้างอิง', 'วันที่จ่าย']);
        \App\Models\DamageClaim::inProvince($p->id)->whereBetween('created_at', [$from, $to])
            ->with('district:id,name_th', 'subdistrict:id,name_th')->orderBy('id')->chunk(500, function ($rows) use ($out, $phones) {
                foreach ($rows as $c) {
                    $this->row($out, [
                        $c->code, $this->dt($c->created_at), $c->head_name, $this->phone($c->phone, $phones), $c->address, $c->district?->name_th, $c->subdistrict?->name_th,
                        $c->members, $c->houseLabel($c->house_damage), $c->verified_damage ? $c->houseLabel($c->verified_damage) : '', implode(', ', $c->lossLabels()),
                        $c->crop_rai, $c->evidence_score, $c->statusLabel(), $c->suggested_amount, $c->approved_amount, $c->paid_amount, $c->payment_ref, $this->dt($c->paid_at),
                    ]);
                }
            });
    }
}
