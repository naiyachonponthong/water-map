<?php

namespace App\Events;

use App\Models\Team;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** ทีมเปลี่ยนสถานะหรือส่งพิกัดใหม่ */
class TeamChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Team $team) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('province.'.$this->team->province_id)];
    }

    public function broadcastAs(): string
    {
        return 'team.changed';
    }

    public function broadcastWith(): array
    {
        $pos = $this->team->position();

        return [
            'id' => $this->team->id,
            'name' => $this->team->name,
            'status' => $this->team->status,
            'status_label' => $this->team->statusLabel(),
            'color' => $this->team->statusColor(),
            'lat' => $pos[0] ?? null,
            'lng' => $pos[1] ?? null,
        ];
    }
}
