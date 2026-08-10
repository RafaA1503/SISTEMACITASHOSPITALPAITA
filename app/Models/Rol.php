<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
    public function paginas(): BelongsToMany { return $this->belongsToMany(Pagina::class,'accesos','idRol','idPagina','idRol','idPagina')->wherePivot('Estado',true); }
    public function acciones(): BelongsToMany { return $this->belongsToMany(Accion::class,'accesosacciones','idRol','idAccion','idRol','idAccion')->wherePivot('Estado',true); }

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
