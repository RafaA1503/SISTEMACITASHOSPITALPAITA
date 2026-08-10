<?php

use App\Http\Controllers\AdminCatalogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DniLookupController;
use App\Http\Controllers\HospitalPortalController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    $module = auth()->user()->defaultModule();
    return redirect()->route('portal', ['role' => $module]);
})->name('inicio');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginView'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/olvide-password', [PasswordResetController::class, 'forgotView'])->name('password.request');
    Route::post('/olvide-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/restablecer-password/{token}', [PasswordResetController::class, 'resetView'])->name('password.reset');
    Route::post('/restablecer-password', [PasswordResetController::class, 'reset'])->name('password.update.reset');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/portal/{role?}', [HospitalPortalController::class, 'index'])->name('portal');

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
    Route::put('/citas/{appointment}/confirmar', [HospitalPortalController::class, 'confirmAppointment'])->name('appointments.confirm');
    Route::put('/citas/{appointment}/no-asistio', [HospitalPortalController::class, 'markNoShow'])->name('appointments.no_show');
    Route::get('/api/portero/pendientes-count', [HospitalPortalController::class, 'pendingCount'])->name('portero.pending_count');
    Route::get('/api/sesion/estado', [AuthController::class, 'sessionStatus'])->name('session.status');
    Route::get('/administracion/reportes', [ReportController::class, 'index'])->name('admin.reports');
    Route::get('/administracion/reportes/excel', [ReportController::class, 'exportExcel'])->name('admin.reports.excel');
    Route::get('/administracion/reportes/pdf', [ReportController::class, 'exportPdf'])->name('admin.reports.pdf');
    Route::get('/administracion/roles', [RoleController::class, 'index'])->name('admin.roles');
    Route::post('/administracion/roles', [RoleController::class, 'store'])->name('admin.roles.store');
    Route::post('/administracion/roles/usuarios', [RoleController::class, 'storeUser'])->name('admin.roles.users.store');
    Route::put('/administracion/roles/{role}', [RoleController::class, 'update'])->name('admin.roles.update');
    Route::delete('/administracion/roles/{role}', [RoleController::class, 'destroy'])->name('admin.roles.destroy');
    Route::put('/administracion/roles/usuarios/{user}', [RoleController::class, 'assign'])->name('admin.roles.assign');
    Route::put('/administracion/roles/usuarios/{user}/estado', [RoleController::class, 'setUserStatus'])->name('admin.roles.users.status');
    Route::put('/administracion/roles/usuarios/{user}/clave', [RoleController::class, 'resetUserPassword'])->name('admin.roles.users.password');
    Route::delete('/administracion/roles/usuarios/{user}', [RoleController::class, 'destroyUser'])->name('admin.roles.users.destroy');

    Route::get('/administracion/servicios', [AdminCatalogController::class, 'index'])->name('admin.catalog');
    Route::post('/administracion/servicios/profesionales', [AdminCatalogController::class, 'storeProfessional'])->name('admin.professionals.store');
    Route::post('/administracion/servicios/turnos', [AdminCatalogController::class, 'storeSchedule'])->name('admin.professionals.schedule.store');
    Route::put('/administracion/servicios/turnos/{schedule}', [AdminCatalogController::class, 'updateSchedule'])->name('admin.professionals.schedule.update');
    Route::delete('/administracion/servicios/turnos/{schedule}', [AdminCatalogController::class, 'destroySchedule'])->name('admin.professionals.schedule.destroy');
});
