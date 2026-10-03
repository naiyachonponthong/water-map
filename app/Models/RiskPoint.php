<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Geo;
use App\Support\RiskOptions;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RiskPoint extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'province_id', 'district_id', 'subdistrict_id', 'type', 'name', 'description', 'lat', 'lng', 'zone',
        'bbox_south', 'bbox_west', 'bbox_north', 'bbox_east', 'radius_m', 'trigger_level', 'severity', 'is_public',
        'status', 'review', 'source', 'proposer_name', 'proposer_phone', 'created_by', 'reviewed_by', 'reviewed_at',
        'threatened_at', 'threat_level', 'threat_reason',
    ];

    protected $hidden = ['proposer_phone'];

    protected array $auditExcept = ['zone', 'threatened_at', 'threat_level', 'threat_reason', 'updated_at'];

    protected function casts(): array
    {
        return [
            'lat' => 'float', 'lng' => 'float', 'is_public' => 'boolean', 'proposer_phone' => 'encrypted',
            'reviewed_at' => 'datetime', 'threatened_at' => 'datetime',
            'bbox_south' => 'float', 'bbox_west' => 'float', 'bbox_north' => 'float', 'bbox_east' => 'float',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function subdistrict(): BelongsTo
    {
        return $this->belongsTo(Subdistrict::class);
    }

    public function scopeInProvince(Builder $q, int $pid): Builder
    {
        return $q->where('province_id', $pid);
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->where('review', 'approved')->where('status', '!=', 'inactive');
    }

    /** ตั้งโซนจาก GeoJSON และให้จุดกลาง/bbox ตามโซน */
    public function setZone(array|string|null $geometry): static
    {
        $geometry = is_string($geometry) ? json_decode($geometry, true) : $geometry;
        if (! $geometry || ! in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
            $this->zone = null;
            $this->bbox_south = $this->bbox_west = $this->bbox_north = $this->bbox_east = null;

            return $this;
        }
        $this->zone = json_encode($geometry);
        [$this->bbox_west, $this->bbox_south, $this->bbox_east, $this->bbox_north] = Geo::bbox($geometry);
        $this->lat = round(($this->bbox_south + $this->bbox_north) / 2, 7);
        $this->lng = round(($this->bbox_west + $this->bbox_east) / 2, 7);

        return $this;
    }

    public function zoneArray(): ?array
    {
        return $this->zone ? json_decode($this->zone, true) : null;
    }

    public function radius(): int
    {
        return (int) ($this->radius_m ?: Settings::get('risk_alert_radius_m', $this->province_id));
    }

    /** จุดนี้อยู่ในรัศมีหรือในโซนหรือไม่ */
    public function covers(float $lat, float $lng): bool
    {
        if ($this->zone && Geo::pointInGeometry($lat, $lng, $this->zoneArray())) {
            return true;
        }

        return Geo::distance($this->lat, $this->lng, $lat, $lng) <= $this->radius();
    }

    public function typeLabel(): string
    {
        return RiskOptions::TYPES[$this->type][0] ?? $this->type;
    }

    public function icon(): string
    {
        return RiskOptions::TYPES[$this->type][1] ?? 'exclamation-triangle';
    }

    public function color(): string
    {
        return $this->status === 'threatened' ? '#dc2626' : (RiskOptions::TYPES[$this->type][2] ?? '#64748b');
    }

    public function statusLabel(): string
    {
        return RiskOptions::STATUS[$this->status][0] ?? $this->status;
    }
}
