<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'custom_role_id',
        'service_id',
        'document_number',
        'active',
        'photo_path',
        'permissions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'permissions' => 'array',
        ];
    }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function customRole(): BelongsTo { return $this->belongsTo(CustomRole::class); }
    public function canAccessModule(string $module): bool
    {
        if ($this->role === 'administrador') return true;
        if ($this->customRole?->active) return in_array($module, $this->customRole->modules ?? [], true);
        return match ($this->role) {
            'portero' => $module === 'portero',
            'admision' => $module === 'citas',
            default => $module === 'servicio',
        };
    }
}
