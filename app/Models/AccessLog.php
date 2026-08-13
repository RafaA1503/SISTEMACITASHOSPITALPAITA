<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessLog extends Model
{
    protected $table = 'access_logs';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['registered_at' => 'datetime'];
    }
}
