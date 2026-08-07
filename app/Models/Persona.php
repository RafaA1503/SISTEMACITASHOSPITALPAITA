<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    protected $table = 'personas';
    protected $primaryKey = 'idPersona';
    public $timestamps = false;
    protected $guarded = [];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->nombres} {$this->apellidoPaterno} {$this->apellidoMaterno}");
    }
}
