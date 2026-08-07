<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    protected $table = 'citas_control_acceso';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'confirmed_at' => 'datetime', 'attention_started_at' => 'datetime', 'attention_completed_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'paciente_id', 'IdPaciente');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'appointment_type_id');
    }

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id', 'idTrabajador');
    }

    /** El profesional (users) ligado al trabajador asignado, si tiene cuenta en el sistema. */
    public function getProfessionalAttribute(): ?User
    {
        $idPersona = $this->trabajador?->idPersona;

        return $idPersona ? User::where('idPersona', $idPersona)->where('idSistema', User::sistemaId())->first() : null;
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class, 'service_area_id');
    }

    public function subarea(): BelongsTo
    {
        return $this->belongsTo(ServiceSubarea::class, 'service_subarea_id');
    }
}
