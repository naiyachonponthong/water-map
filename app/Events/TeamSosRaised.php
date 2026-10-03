<?php

namespace App\Events;

use App\Models\TeamSos;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** ทีมกด SOS หรือศูนย์รับทราบ/ปิด SOS */
class TeamSosRaised implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public TeamSos $sos) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('province.'.$this->sos->province_id),
            new PrivateChannel('team.'.$this->sos->team_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'team.sos';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->sos->id,
            'team_id' => $this->sos->team_id,
            'team' => $this->sos->team?->name,
            'kind' => $this->sos->kindLabel(),
            'status' => $this->sos->status,
            'lat' => $this->sos->lat,
            'lng' => $this->sos->lng,
        ];
    }
}
