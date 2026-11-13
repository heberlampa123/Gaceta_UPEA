<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Panel\AuditoriaController;
use App\Http\Controllers\Panel\DocumentoController;
use App\Http\Controllers\Panel\FlujoDocumentalController;
use App\Http\Controllers\Panel\InicioController;
use App\Http\Controllers\Panel\NotificacionController as PanelNotificacionController;
use App\Http\Controllers\Panel\PerfilController;
use App\Http\Controllers\Panel\RelacionDocumentalController;
use App\Http\Controllers\Panel\RevisionController;
use App\Http\Controllers\Panel\RolController;
use App\Http\Controllers\Panel\TipoDocumentalController;
use App\Http\Controllers\Panel\UsuarioController;
use App\Http\Controllers\Panel\VersionDocumentoController;
use App\Http\Controllers\Portal\PortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portal público (Fase 6: búsqueda y consulta de documentos publicados)
|--------------------------------------------------------------------------
*/
Route::get('/', [PortalController::class, 'index'])->name('portal.inicio');
Route::view('/acerca', 'portal.acerca')->name('portal.acerca');
Route::get('/documentos/{documento}', [PortalController::class, 'show'])->whereNumber('documento')->name('portal.documentos.show');
Route::get('/documentos/{documento}/pdf', [PortalController::class, 'pdf'])->whereNumber('documento')
    ->middleware('throttle:descargas')->name('portal.documentos.pdf');

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
| "auth.session": cierra las demás sesiones cuando cambia la contraseña (corrección 13/11/2026).
| "cuenta": cierra sesiones de cuentas desactivadas y obliga a cambiar la contraseña temporal.
| "can:<permiso>": autorización por código de permiso (ver AppServiceProvider).
*/
Route::prefix('panel')->name('panel.')->middleware(['auth', 'auth.session', 'cuenta'])->group(function () {
    Route::get('/', InicioController::class)->name('inicio');

    // Mi perfil
    Route::get('/perfil', [PerfilController::class, 'show'])->name('perfil');
    Route::get('/perfil/contrasena', [PerfilController::class, 'editarContrasena'])->name('perfil.contrasena');
    Route::put('/perfil/contrasena', [PerfilController::class, 'actualizarContrasena'])->name('perfil.contrasena.actualizar');

    // Notificaciones (Fase 7)
    Route::get('/notificaciones', [PanelNotificacionController::class, 'index'])->name('notificaciones.index');
    Route::get('/notificaciones/{notificacion}/abrir', [PanelNotificacionController::class, 'abrir'])->name('notificaciones.abrir');
    Route::post('/notificaciones/leer-todas', [PanelNotificacionController::class, 'leerTodas'])->name('notificaciones.leer-todas');

    // Auditoría (Fase 7)
    Route::middleware('can:auditoria.ver')->group(function () {
        Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
        Route::get('/auditoria/exportar', [AuditoriaController::class, 'exportar'])->name('auditoria.exportar');
    });

    // Gestión documental (Fase 3)
    Route::middleware('can:documento.ver')->group(function () {
        Route::get('/documentos', [DocumentoController::class, 'index'])->name('documentos.index');
        Route::get('/documentos/registrar', [DocumentoController::class, 'create'])->middleware('can:documento.registrar')->name('documentos.create');
        Route::post('/documentos', [DocumentoController::class, 'store'])->middleware('can:documento.registrar')->name('documentos.store');
        Route::get('/documentos/{documento}', [DocumentoController::class, 'show'])->name('documentos.show');
        // Edición y nuevas versiones: el permiso y el estado del documento se verifican en DocumentoPolicy.
        Route::get('/documentos/{documento}/editar', [DocumentoController::class, 'edit'])->name('documentos.edit');
        Route::put('/documentos/{documento}', [DocumentoController::class, 'update'])->name('documentos.update');
        Route::post('/documentos/{documento}/anular', [DocumentoController::class, 'anular'])->name('documentos.anular');

        Route::post('/documentos/{documento}/versiones', [VersionDocumentoController::class, 'store'])->name('documentos.versiones.store');
        Route::get('/documentos/{documento}/versiones/{version}/archivo', [VersionDocumentoController::class, 'archivo'])->name('documentos.versiones.archivo');
        Route::post('/documentos/{documento}/versiones/{version}/verificar', [VersionDocumentoController::class, 'verificar'])->name('documentos.versiones.verificar');

        // Relaciones entre normas (abroga / deroga / modifica): mismo permiso y estados que «Editar datos».
        Route::get('/documentos/{documento}/relaciones/candidatos', [RelacionDocumentalController::class, 'candidatos'])->name('documentos.relaciones.candidatos');
        Route::post('/documentos/{documento}/relaciones', [RelacionDocumentalController::class, 'store'])->name('documentos.relaciones.store');
        Route::delete('/documentos/{documento}/relaciones/{relacion}', [RelacionDocumentalController::class, 'destroy'])->name('documentos.relaciones.destroy');

        // Flujo de revisión y publicación (Fase 5): permiso, estado y revisor distinto en DocumentoPolicy.
        Route::controller(FlujoDocumentalController::class)->prefix('/documentos/{documento}')->name('documentos.')->group(function () {
            Route::post('/enviar-revision', 'enviar')->name('enviar');
            Route::post('/reenviar', 'reenviar')->name('reenviar');
            Route::post('/devolver', 'devolver')->name('devolver');
            Route::post('/aprobar', 'aprobar')->name('aprobar');
            Route::post('/publicar', 'publicar')->name('publicar');
        });
    });

    // Bandeja del Revisor / Publicador (Fase 5)
    Route::get('/revision', [RevisionController::class, 'index'])->middleware('can:documento.revisar')->name('revision.index');

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
