<?php

namespace App\Models;

use App\Support\StationOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RainForecast extends Model
{
    public $timestamps = false;

    protected $fillable = ['province_id', 'district_id', 'date', 'rain_mm', 'rain_prob', 'temp_max', 'source', 'fetched_at'];

    protected function casts(): array
    {
        return ['date' => 'date', 'rain_mm' => 'float', 'temp_max' => 'float', 'fetched_at' => 'datetime'];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function category(): ?array
    {
        return StationOptions::rain($this->rain_mm);
    }
}
