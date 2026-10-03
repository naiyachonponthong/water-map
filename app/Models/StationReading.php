<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StationReading extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['water_station_id', 'value', 'measured_at', 'source', 'user_id'];

    protected function casts(): array
    {
        return ['value' => 'float', 'measured_at' => 'datetime'];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(WaterStation::class, 'water_station_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
