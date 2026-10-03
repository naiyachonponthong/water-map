<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyContact extends Model
{
    use Auditable;

    protected $fillable = ['province_id', 'label', 'phone', 'note', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function telUri(): string
    {
        return 'tel:'.preg_replace('/\D+/', '', $this->phone);
    }
}
