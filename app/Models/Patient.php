<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Apunta directo a la tabla `pacientes` real de SIGESA. Los accessors/mutators
 * mantienen los nombres de atributo que ya usa el resto de la app
 * (first_names, dni, sex, etc.) para minimizar cambios en controladores/vistas.
 */
class Patient extends Model
{
    protected $table = 'pacientes';
    protected $primaryKey = 'IdPaciente';
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['FechaNacimiento' => 'date'];
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'paciente_id', 'IdPaciente');
    }

    // --- Identidad ---
    public function getDniAttribute(): ?string { return $this->NroDocumento; }
    public function setDniAttribute($value): void { $this->attributes['NroDocumento'] = $value; }

    public function getFirstNamesAttribute(): ?string { return $this->PrimerNombre; }
    public function setFirstNamesAttribute($value): void { $this->attributes['PrimerNombre'] = $value; }

    public function getSecondNameAttribute(): ?string { return $this->SegundoNombre; }
    public function setSecondNameAttribute($value): void { $this->attributes['SegundoNombre'] = $value; }

    public function getThirdNameAttribute(): ?string { return $this->TercerNombre; }
    public function setThirdNameAttribute($value): void { $this->attributes['TercerNombre'] = $value; }

    public function getPaternalSurnameAttribute(): ?string { return $this->ApellidoPaterno; }
    public function setPaternalSurnameAttribute($value): void { $this->attributes['ApellidoPaterno'] = $value; }

    public function getMaternalSurnameAttribute(): ?string { return $this->ApellidoMaterno; }
    public function setMaternalSurnameAttribute($value): void { $this->attributes['ApellidoMaterno'] = $value; }

    public function getLastNamesAttribute(): string
    {
        return trim("{$this->ApellidoPaterno} {$this->ApellidoMaterno}");
    }

    public function getDocumentTypeAttribute(): string { return 'DNI'; }

    // --- Datos de contacto/demográficos ---
    public function getBirthDateAttribute() { return $this->FechaNacimiento; }
    public function setBirthDateAttribute($value): void { $this->attributes['FechaNacimiento'] = $value; }

    public function getSexAttribute(): ?string
    {
        return match ($this->IdTipoSexo) { 1 => 'M', 2 => 'F', 3 => 'O', default => null };
    }

    public function setSexAttribute(?string $value): void
    {
        $this->attributes['IdTipoSexo'] = match ($value) { 'M' => 1, 'F' => 2, 'O' => 3, default => null };
    }

    public function getPhoneAttribute(): ?string { return $this->Telefono ?: $this->celular; }
    public function setPhoneAttribute($value): void { $this->attributes['Telefono'] = $value; }

    public function getAddressAttribute(): ?string { return $this->DireccionDomicilio; }
    public function setAddressAttribute($value): void { $this->attributes['DireccionDomicilio'] = $value; }

    public function getEmailAttribute(): ?string { return $this->Email; }
    public function setEmailAttribute($value): void { $this->attributes['Email'] = $value; }

    public function getBloodGroupAttribute(): ?string { return $this->GrupoSanguineo; }
    public function setBloodGroupAttribute($value): void { $this->attributes['GrupoSanguineo'] = $value; }

    public function getFatherNameAttribute(): ?string { return $this->NombrePadre; }
    public function setFatherNameAttribute($value): void { $this->attributes['NombrePadre'] = $value; }

    public function getMotherNameAttribute(): ?string { return $this->NombreMadre; }
    public function setMotherNameAttribute($value): void { $this->attributes['NombreMadre'] = $value; }

    public function getMedicalRecordNumberAttribute(): ?string { return $this->NroHistoriaClinica; }
    public function setMedicalRecordNumberAttribute($value): void { $this->attributes['NroHistoriaClinica'] = $value; }

    public function getInsuranceAttribute(): ?string { return $this->insurance_note; }
    public function setInsuranceAttribute($value): void { $this->attributes['insurance_note'] = $value; }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_names, $this->second_name, $this->third_name, $this->paternal_surname, $this->maternal_surname])))
            ?: trim("{$this->first_names} {$this->last_names}");
    }
}
