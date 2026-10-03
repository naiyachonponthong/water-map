<?php

namespace App\Models;

use App\Support\ReliefOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyItem extends Model
{
    protected $fillable = ['province_id', 'name', 'category', 'unit', 'min_stock', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(SupplyMovement::class);
    }

    public function scopeInProvince(Builder $q, int $pid): Builder
    {
        return $q->where('province_id', $pid);
    }

    public function categoryLabel(): string
    {
        return ReliefOptions::SUPPLY_CATEGORIES[$this->category][0] ?? $this->category;
    }

    public function icon(): string
    {
        return ReliefOptions::SUPPLY_CATEGORIES[$this->category][1] ?? 'box';
    }
}
