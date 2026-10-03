<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RunningNumber extends Model
{
    protected $fillable = ['key', 'value'];

    /** เลขถัดไปของ key นี้ (ล็อกแถวกันเลขซ้ำเมื่อมีคำขอเข้าพร้อมกัน) */
    public static function next(string $key): int
    {
        return DB::transaction(function () use ($key) {
            $row = static::where('key', $key)->lockForUpdate()->first();
            if (! $row) {
                try {
                    $row = static::create(['key' => $key, 'value' => 0]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                    $row = static::where('key', $key)->lockForUpdate()->firstOrFail();
                }
            }
            $row->increment('value');

            return $row->value;
        });
    }
}
