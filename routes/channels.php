<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canales de actualizaciones en vivo, uno por pantalla. Solo usuarios del
// mismo "sistema" (ControlAccesoPacientes) pueden escuchar, igual que ya se
// escopa el login y las consultas en User::sistemaId().
foreach (['citas', 'admin.schedules', 'admin.roles', 'admin.users', 'admin.professionals'] as $channel) {
    Broadcast::channel($channel, function (User $user) {
        return $user->idSistema === User::sistemaId();
    });
}
