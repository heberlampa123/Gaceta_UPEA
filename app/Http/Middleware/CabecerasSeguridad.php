<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad para todas las respuestas (RNF-01).
 *
 *  - X-Frame-Options: SAMEORIGIN    → otras páginas no pueden incrustar el sistema (clickjacking).
 *                                      Se permite el mismo origen para el visor PDF del panel y del portal.
 *  - X-Content-Type-Options: nosniff → el navegador no "adivina" tipos de archivo.
 *  - Referrer-Policy                 → no envía la dirección completa a sitios externos.
 *  - Permissions-Policy              → desactiva cámara, micrófono y ubicación.
 *  - Strict-Transport-Security       → solo en producción con HTTPS: obliga a usar HTTPS.
 */
class CabecerasSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $respuesta->headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff', false);
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $respuesta->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()', false);

        if ($request->isSecure() && app()->isProduction()) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        return $respuesta;
    }
}
