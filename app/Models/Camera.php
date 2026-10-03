<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\StationOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Camera extends Model
{
    use Auditable;

    protected $fillable = ['province_id', 'water_station_id', 'name', 'lat', 'lng', 'type', 'url', 'refresh_sec', 'owner', 'is_public', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float', 'is_public' => 'boolean', 'is_active' => 'boolean'];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(WaterStation::class, 'water_station_id');
    }

    public function scopeInProvince(Builder $q, int $pid): Builder
    {
        return $q->where('province_id', $pid);
    }

    public function typeLabel(): string
    {
        return StationOptions::CAMERA_TYPES[$this->type][0] ?? $this->type;
    }

    public function icon(): string
    {
        return StationOptions::CAMERA_TYPES[$this->type][1] ?? 'camera-video';
    }

    /** ข้อมูลที่ส่งให้หน้าเว็บ */
    public function toViewer(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'type' => $this->type, 'url' => $this->url, 'refresh' => max(10, (int) $this->refresh_sec), 'owner' => $this->owner, 'lat' => $this->lat, 'lng' => $this->lng];
    }
}
