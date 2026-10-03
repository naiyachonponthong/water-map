<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShelterNeed extends Model
{
    protected $fillable = ['shelter_id', 'supply_item_id', 'item', 'qty', 'unit', 'priority', 'status', 'created_by'];

    public function shelter(): BelongsTo
    {
        return $this->belongsTo(Shelter::class);
    }

    public function supplyItem(): BelongsTo
    {
        return $this->belongsTo(SupplyItem::class);
    }
}
