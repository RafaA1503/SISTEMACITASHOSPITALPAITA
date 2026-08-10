<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

/**
 * Apunta directo a la tabla `users` real de SIGESA (id/correo/usuario/password/
 * idRol/idSistema/idPersona/Estado). Nunca se le agregan columnas: todo lo
 * propio de esta app vive en `user_profiles` (1:1) o se resuelve vía
 * `idPersona` -> `trabajador` -> servicio.
 */
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable;

    public const SISTEMA_NOMBRE = 'ControlAccesoPacientes';

    protected $fillable = [
        'correo', 'usuario', 'password', 'idPersona', 'idOficina',
        'idRol', 'idSistema', 'idTipoUsers', 'Estado', 'IdPacienteReniec',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'Estado' => 'boolean',
        ];
    }

    public static function sistemaId(): int
    {
        return Cache::rememberForever('sistema_control_acceso_pacientes_id', function () {
            return (int) \Illuminate\Support\Facades\DB::table('sistemas')->where('nombre', self::SISTEMA_NOMBRE)->value('idSistema');
        });
    }

    /** Usuarios de nuestro sistema con alguno de los roles dados, activos. */
    public static function withRoles(array $roleNames): \Illuminate\Support\Collection
    {
        $roleIds = Rol::where('idSistema', self::sistemaId())->whereIn('nombreRol', $roleNames)->pluck('idRol');

        return static::where('idSistema', self::sistemaId())->whereIn('idRol', $roleIds)->where('Estado', true)
            ->with(['persona', 'trabajadorRecord'])->get()->sortBy('name')->values();
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'idPersona', 'idPersona');
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'idRol', 'idRol');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function trabajadorRecord(): HasOne
    {
        return $this->hasOne(Trabajador::class, 'idPersona', 'idPersona')->where('estado', true);
    }

    /** Turnos del trabajador ligado a este usuario (vía idPersona -> trabajador). */
    public function schedules(): \Illuminate\Database\Eloquent\Builder
    {
        return ProfessionalSchedule::where('trabajador_id', $this->trabajadorRecord?->idTrabajador ?? 0);
    }

    // --- Accessors que mantienen los nombres de atributo que ya usa el resto de la app ---

    public function getNameAttribute(): string
    {
        return $this->persona?->full_name ?: ($this->usuario ?? $this->correo ?? 'Usuario');
    }

    public function getEmailAttribute(): ?string
    {
        return $this->correo;
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['correo'] = $value;
    }

    public function getRoleAttribute(): ?string
    {
        // El catálogo legacy de roles del hospital usa mayúsculas ("Administrador");
        // el resto del código compara en minúsculas, así que se normaliza aquí una
        // sola vez en vez de tocar cada comparación.
        return $this->rol ? mb_strtolower($this->rol->nombreRol) : null;
    }

    public function getActiveAttribute(): bool
    {
        return (bool) $this->Estado;
    }

    public function getPhotoPathAttribute(): ?string
    {
        return $this->profile?->photo_path;
    }

    public function getPermissionsAttribute(): array
    {
        return $this->profile?->permissions ?? [];
    }

    public function getServiceIdAttribute(): ?int
    {
        $legacyServicioId = $this->trabajadorRecord?->idServicio;

        return $legacyServicioId ? Service::where('legacy_id', $legacyServicioId)->value('id') : null;
    }

    public function getServiceAttribute(): ?Service
    {
        return $this->service_id ? Service::find($this->service_id) : null;
    }

    public function canAccessModule(string $module): bool
    {
        $page = ['portero' => 'control_acceso', 'administrador' => 'administracion'][$module] ?? null;
        if (! $page || ! $this->rol) return false;
        return $this->rol->paginas()->where('paginas.descripcion', $page)->exists();
    }

    /** Módulo al que debe entrar el usuario tras iniciar sesión. */
    public function defaultModule(): string
    {
        return $this->canAccessModule('administrador') ? 'administrador' : 'portero';
    }
}
