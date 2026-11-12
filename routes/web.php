<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Panel\DocumentoController;
use App\Http\Controllers\Panel\InicioController;
use App\Http\Controllers\Panel\PerfilController;
use App\Http\Controllers\Panel\RolController;
use App\Http\Controllers\Panel\TipoDocumentalController;
use App\Http\Controllers\Panel\UsuarioController;
use App\Http\Controllers\Panel\VersionDocumentoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portal público (Fase 6: búsqueda y consulta de documentos publicados)
|--------------------------------------------------------------------------
*/
Route::view('/', 'portal.inicio')->name('portal.inicio');

/*
|--------------------------------------------------------------------------
| Autenticación (no existe registro público: las cuentas las crea el administrador)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Panel administrativo (Figura 2)
|--------------------------------------------------------------------------
| "cuenta": cierra sesiones de cuentas desactivadas y obliga a cambiar la contraseña temporal.
| "can:<permiso>": autorización por código de permiso (ver AppServiceProvider).
*/
Route::prefix('panel')->name('panel.')->middleware(['auth', 'cuenta'])->group(function () {
    Route::get('/', InicioController::class)->name('inicio');

    // Mi perfil
    Route::get('/perfil', [PerfilController::class, 'show'])->name('perfil');
    Route::get('/perfil/contrasena', [PerfilController::class, 'editarContrasena'])->name('perfil.contrasena');
    Route::put('/perfil/contrasena', [PerfilController::class, 'actualizarContrasena'])->name('perfil.contrasena.actualizar');

    // Gestión documental (Fase 3)
    Route::middleware('can:documento.ver')->group(function () {
        Route::get('/documentos', [DocumentoController::class, 'index'])->name('documentos.index');
        Route::get('/documentos/registrar', [DocumentoController::class, 'create'])->middleware('can:documento.registrar')->name('documentos.create');
        Route::post('/documentos', [DocumentoController::class, 'store'])->middleware('can:documento.registrar')->name('documentos.store');
        Route::get('/documentos/{documento}', [DocumentoController::class, 'show'])->name('documentos.show');
        // Edición y nuevas versiones: el permiso y el estado del documento se verifican en DocumentoPolicy.
        Route::get('/documentos/{documento}/editar', [DocumentoController::class, 'edit'])->name('documentos.edit');
        Route::put('/documentos/{documento}', [DocumentoController::class, 'update'])->name('documentos.update');

        Route::post('/documentos/{documento}/versiones', [VersionDocumentoController::class, 'store'])->name('documentos.versiones.store');
        Route::get('/documentos/{documento}/versiones/{version}/archivo', [VersionDocumentoController::class, 'archivo'])->name('documentos.versiones.archivo');
        Route::post('/documentos/{documento}/versiones/{version}/verificar', [VersionDocumentoController::class, 'verificar'])->name('documentos.versiones.verificar');
    });

    // Tipos documentales (Fase 4)
    Route::middleware('can:tipo_documental.gestionar')->group(function () {
        Route::resource('tipos-documentales', TipoDocumentalController::class)
            ->except(['show', 'destroy'])
            ->parameters(['tipos-documentales' => 'tipo'])
            ->names('tipos');
    });

    // Usuarios
    Route::middleware('can:usuario.gestionar')->group(function () {
        Route::resource('usuarios', UsuarioController::class)
            ->except(['show', 'destroy'])
            ->parameters(['usuarios' => 'usuario']);

        Route::controller(UsuarioController::class)->prefix('usuarios/{usuario}')->name('usuarios.')->group(function () {
            Route::patch('/desactivar', 'desactivar')->name('desactivar');
            Route::patch('/activar', 'activar')->name('activar');
            Route::patch('/desbloquear', 'desbloquear')->name('desbloquear');
            Route::patch('/restablecer-contrasena', 'restablecerContrasena')->name('restablecer-contrasena');
        });
    });

    // Roles y permisos
    Route::middleware('can:rol.gestionar')->group(function () {
        Route::resource('roles', RolController::class)
            ->except(['show', 'destroy'])
            ->parameters(['roles' => 'rol']);
    });
});
