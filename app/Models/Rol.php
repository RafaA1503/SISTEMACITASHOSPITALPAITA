<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'idRol';
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }
}
