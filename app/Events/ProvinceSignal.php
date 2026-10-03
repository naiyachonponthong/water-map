<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** สัญญาณทั่วไปของจังหวัด เช่น risk.changed ให้หน้าจอโหลดข้อมูลใหม่ */
class ProvinceSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $provinceId, public string $name, public array $data = []) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('province.'.$this->provinceId)];
    }

    public function broadcastAs(): string
    {
        return $this->name;
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
