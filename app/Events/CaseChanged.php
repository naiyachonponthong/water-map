<?php

namespace App\Events;

use App\Models\HelpRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * เคสเปลี่ยน (สร้างใหม่ เปลี่ยนสถานะ ผู้แจ้งอัปเดต ฯลฯ) ส่งข้อมูลย่อพอให้หน้าจอรู้ว่าต้องโหลดใหม่
 * ไม่ส่งชื่อ/เบอร์ผู้แจ้งผ่าน socket
 */
class CaseChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public HelpRequest $case, public string $type, public ?string $summary = null) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('province.'.$this->case->province_id)];
        if ($this->case->team_id) {
            $channels[] = new PrivateChannel('team.'.$this->case->team_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'case.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->case->id,
            'code' => $this->case->code,
            'type' => $this->type,
            'status' => $this->case->status,
            'priority' => $this->case->priority,
            'critical' => $this->case->priority === 'critical',
            'team_id' => $this->case->team_id,
            'summary' => $this->summary,
        ];
    }
}
