<?php

use App\Http\Middleware\VerificarCuenta;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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

        // Visitantes sin sesión que intentan entrar al panel → inicio de sesión.
        // Usuarios con sesión que abren /login → panel.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('panel.inicio'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
