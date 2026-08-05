<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DniLookupController;

Route::get('/', function () {
    return view('welcome');
})->name('inicio');

Route::view('/citas', 'modules', ['module' => 'citas'])->name('citas');
Route::view('/pacientes', 'modules', ['module' => 'pacientes'])->name('pacientes');
Route::view('/control-acceso', 'modules', ['module' => 'accesos'])->name('accesos');
Route::view('/historial', 'modules', ['module' => 'historial'])->name('historial');
Route::view('/configuracion', 'modules', ['module' => 'configuracion'])->name('configuracion');
Route::view('/ayuda', 'modules', ['module' => 'ayuda'])->name('ayuda');
Route::get('/api/consultar-dni/{dni}', DniLookupController::class)->whereNumber('dni')->middleware('throttle:30,1')->name('dni.consultar');
