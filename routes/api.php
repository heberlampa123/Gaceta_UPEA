<?php

use App\Http\Controllers\Api\DocumentoPublicoController;
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

// Protegida con Sanctum (sesión del panel o token).
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
