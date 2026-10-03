<?php

namespace App\Models;

use App\Support\RiskOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdCheck extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['vulnerable_household_id', 'user_id', 'team_id', 'status', 'note', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class)->withTrashed();
    }

    public function statusLabel(): string
    {
        return RiskOptions::CHECK[$this->status][0] ?? $this->status;
    }

    public function statusChip(): string
    {
        return RiskOptions::CHECK[$this->status][1] ?? '';
    }
}
