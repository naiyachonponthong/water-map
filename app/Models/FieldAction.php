<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldAction extends Model
{
    protected $fillable = ['key', 'user_id', 'type', 'payload', 'result', 'ok', 'client_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'result' => 'array', 'ok' => 'boolean', 'client_at' => 'datetime'];
    }
}
