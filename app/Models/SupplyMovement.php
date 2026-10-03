<?php

namespace App\Models;

use App\Support\ReliefOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['province_id', 'supply_item_id', 'shelter_id', 'qty', 'kind', 'ref', 'donor', 'help_request_id', 'note', 'user_id', 'created_at'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(SupplyItem::class, 'supply_item_id');
    }

    public function shelter(): BelongsTo
    {
        return $this->belongsTo(Shelter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function helpRequest(): BelongsTo
    {
        return $this->belongsTo(HelpRequest::class);
    }

    public function kindLabel(): string
    {
        return ReliefOptions::MOVEMENT_KINDS[$this->kind] ?? $this->kind;
    }
}
