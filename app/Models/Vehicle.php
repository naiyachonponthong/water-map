<?php

namespace App\Models;

use App\Support\TeamOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    protected $fillable = ['team_id', 'type', 'name', 'plate', 'capacity', 'status'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function typeLabel(): string
    {
        return TeamOptions::VEHICLES[$this->type][0] ?? $this->type;
    }

    public function icon(): string
    {
        return TeamOptions::VEHICLES[$this->type][1] ?? 'gear';
    }
}
