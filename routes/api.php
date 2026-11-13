<?php

use App\Http\Controllers\Api\DocumentoPublicoController;
use App\Http\Controllers\Api\NotificacionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API REST (JSON) — prefijo /api
|--------------------------------------------------------------------------
*/

// Pública: búsqueda de documentos publicados (portal con Axios, Fase 6). Con límite de consultas por IP.
Route::get('/documentos', [DocumentoPublicoController::class, 'index'])
    ->middleware('throttle:portal')
    ->name('api.documentos.index');

// Protegidas con Sanctum (sesión del panel o token). "cuenta": rechaza cuentas desactivadas.
Route::middleware(['auth:sanctum', 'cuenta'])->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    // Notificaciones del usuario (campana del panel con Axios, Fase 7).
    Route::get('/notificaciones/resumen', [NotificacionController::class, 'resumen'])->name('api.notificaciones.resumen');
    Route::patch('/notificaciones/{notificacion}/leer', [NotificacionController::class, 'leer'])->name('api.notificaciones.leer');
    Route::post('/notificaciones/leer-todas', [NotificacionController::class, 'leerTodas'])->name('api.notificaciones.leer-todas');
});
