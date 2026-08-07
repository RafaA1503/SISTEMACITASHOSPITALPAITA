<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trabajador extends Model
{
    protected $table = 'trabajador';
    protected $primaryKey = 'idTrabajador';
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'idPersona', 'idPersona');
    }

    /** Servicio propio (catálogo interno) ligado por legacy_id = servicios.IdServicio. */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'idServicio', 'legacy_id');
    }
}
