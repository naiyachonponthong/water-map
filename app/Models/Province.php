<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'slug', 'name_th', 'name_en', 'region', 'center_lat', 'center_lng', 'default_zoom',
        'is_active', 'command_open', 'web_help_open', 'command_opened_at',
    ];

    protected function casts(): array
    {
        return [
            'center_lat' => 'float',
            'center_lng' => 'float',
            'is_active' => 'boolean',
            'command_open' => 'boolean',
            'web_help_open' => 'boolean',
            'command_opened_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class)->orderBy('sort')->orderBy('name_th');
    }

    public function subdistricts(): HasMany
    {
        return $this->hasMany(Subdistrict::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class)->orderBy('sort');
    }

    public function externalLinks(): HasMany
    {
        return $this->hasMany(ExternalLink::class)->orderBy('sort');
    }

    /** ชื่อแบบ "จ.ชลบุรี" (กรุงเทพมหานครไม่ใส่ จ.) */
    public function shortName(): string
    {
        return $this->code === '10' ? $this->name_th : 'จ.'.$this->name_th;
    }

    public function fullName(): string
    {
        return $this->code === '10' ? $this->name_th : 'จังหวัด'.$this->name_th;
    }

    public function hasCenter(): bool
    {
        return $this->center_lat !== null && $this->center_lng !== null;
    }

    /** เบอร์ฉุกเฉินที่ใช้แสดง: ของจังหวัดถ้ามี ไม่มีใช้เบอร์ระดับประเทศ */
    public function publicContacts(): array
    {
        $own = $this->emergencyContacts()->where('is_active', true)->get(['label', 'phone'])->toArray();

        return $own ?: config('floodthai.national_contacts');
    }
}
