<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['damage_claim_id', 'user_id', 'type', 'note', 'public', 'created_at'];

    protected function casts(): array
    {
        return ['public' => 'boolean', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
