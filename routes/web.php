<?php

use App\Http\Controllers\Nodo\BloqueosController;
use App\Http\Controllers\Nodo\DisponibilidadController;
use App\Http\Controllers\Nodo\ReservasController;
use Illuminate\Support\Facades\Route;

// La página principal es Disponibilidad; sin sesión, el middleware auth lleva al login.
Route::redirect('/', '/disponibilidad');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::redirect('/dashboard', '/disponibilidad')->name('dashboard');

    Route::get('/disponibilidad', [DisponibilidadController::class, 'index'])->name('disponibilidad');
    Route::get('/reservas', [ReservasController::class, 'index'])->name('reservas.index');
    Route::post('/reservas', [ReservasController::class, 'store'])->name('reservas.store');
    Route::post('/reservas/{reserva}/cancelar', [ReservasController::class, 'cancelar'])->name('reservas.cancelar');
    Route::post('/bloqueos', [BloqueosController::class, 'store'])->name('bloqueos.store');
    Route::delete('/bloqueos/{sala}/{fecha}/{bloqueo}', [BloqueosController::class, 'destroy'])->name('bloqueos.destroy');
});
