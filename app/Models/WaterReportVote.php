<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterReportVote extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['water_report_id', 'voter_hash', 'kind', 'created_at'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(WaterReport::class, 'water_report_id');
    }
}
