<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Se aplica a todas las rutas del panel:
 *  - Cierra la sesión si la cuenta fue desactivada mientras estaba conectada.
 *  - Obliga a cambiar la contraseña asignada por el administrador antes de continuar.
 */
class VerificarCuenta
{
    /** Rutas permitidas mientras el usuario debe cambiar su contraseña. */
    private const RUTAS_PERMITIDAS = ['panel.perfil.contrasena', 'panel.perfil.contrasena.actualizar', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->activo) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Su cuenta fue desactivada.'], 403);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'usuario' => 'Su cuenta fue desactivada. Comuníquese con el administrador.',
            ]);
        }

        if ($usuario?->debe_cambiar_contrasena && ! $request->routeIs(...self::RUTAS_PERMITIDAS)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Debe cambiar su contraseña antes de continuar.'], 403);
            }

            return redirect()->route('panel.perfil.contrasena')
                ->with('aviso', 'Por seguridad, debe cambiar su contraseña antes de continuar.');
        }

        return $next($request);
    }
}
