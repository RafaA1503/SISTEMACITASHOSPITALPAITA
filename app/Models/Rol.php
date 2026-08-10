<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'idRol', 'idRol');
    }

    // Alias para que las vistas compartidas con el antiguo modelo CustomRole
    // (que usaba id/name) sigan funcionando sin cambiar cada plantilla.
    public function getIdAttribute()
    {
        return $this->idRol;
    }

    public function getNameAttribute(): ?string
    {
        return $this->nombreRol;
    }
}
