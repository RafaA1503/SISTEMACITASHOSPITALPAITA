<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DniLookupController;
use App\Http\Controllers\HospitalPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return redirect()->route('portal', ['role' => auth()->user()->role]);
})->name('inicio');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginView'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/portal/{role?}', [HospitalPortalController::class, 'index'])->name('portal');

    Route::view('/citas', 'modules', ['module' => 'citas'])->name('citas');
    Route::view('/pacientes', 'modules', ['module' => 'pacientes'])->name('pacientes');
    Route::view('/control-acceso', 'modules', ['module' => 'accesos'])->name('accesos');
    Route::view('/historial', 'modules', ['module' => 'historial'])->name('historial');
    Route::view('/configuracion', 'modules', ['module' => 'configuracion'])->name('configuracion');
    Route::view('/ayuda', 'modules', ['module' => 'ayuda'])->name('ayuda');

    Route::get('/configuracion/cuenta', [AuthController::class, 'settings'])->name('account.settings');
    Route::get('/configuracion/foto', [AuthController::class, 'photo'])->name('profile.photo');
    Route::put('/configuracion/perfil', [AuthController::class, 'updateProfile'])->name('profile.update');
    Route::put('/configuracion/clave', [AuthController::class, 'password'])->name('password.update');

    Route::get('/administracion/usuarios', [AuthController::class, 'users'])->name('admin.users');
    Route::put('/administracion/usuarios/{user}', [AuthController::class, 'updateUser'])->name('admin.users.update');

    Route::get('/api/consultar-dni/{dni}', DniLookupController::class)
        ->whereNumber('dni')
        ->middleware('throttle:30,1')
        ->name('dni.consultar');
    Route::get('/api/pacientes/{dni}/citas', [HospitalPortalController::class, 'patient'])
        ->whereNumber('dni')
        ->name('patients.appointments');
    Route::post('/api/accesos', [HospitalPortalController::class, 'registerAccess'])->name('access.store');
});
