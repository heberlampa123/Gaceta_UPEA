<?php

use App\Http\Middleware\CabecerasSeguridad;
use App\Http\Middleware\VerificarCuenta;
use App\Support\LimitesArchivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'cuenta' => VerificarCuenta::class,
        ]);

        // Sanctum: las rutas de /api reconocen la sesión del panel (para las llamadas con Axios),
        // con protección CSRF, cuando la petición viene de un dominio configurado (config/sanctum.php).
        $middleware->statefulApi();

        // Cabeceras de seguridad en todas las respuestas (Fase 8).
        $middleware->append(CabecerasSeguridad::class);

        // Visitantes sin sesión que intentan entrar al panel → inicio de sesión.
        // Usuarios con sesión que abren /login → panel.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('panel.inicio'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // El envío supera post_max_size de PHP (por ejemplo, un PDF demasiado grande).
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $mensaje = 'El archivo supera el tamaño máximo que permite el servidor ('.LimitesArchivo::pdfMaxEfectivoMb().' MB).';

            return $request->expectsJson()
                ? response()->json(['message' => $mensaje], 413)
                : back()->with('error', $mensaje);
        });
    })->create();
