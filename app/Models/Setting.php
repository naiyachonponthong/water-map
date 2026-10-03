<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    use Auditable;

    protected $fillable = ['province_id', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
