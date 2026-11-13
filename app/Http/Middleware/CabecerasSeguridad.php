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
    /**
     * Content-Security-Policy (corrección 13/11/2026): solo se permiten scripts, estilos, imágenes,
     * fuentes y conexiones del propio sistema. 'unsafe-inline' en estilos es necesario para los
     * atributos style de las vistas; los scripts en línea NO están permitidos.
     */
    private const POLITICA_CONTENIDO = [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data:",
        "font-src 'self' data:",
        "connect-src 'self'",
        "frame-src 'self'",
        "frame-ancestors 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $respuesta->headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff', false);
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $respuesta->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()', false);

        if ($this->aplicaPoliticaDeContenido($respuesta)) {
            $respuesta->headers->set('Content-Security-Policy', implode('; ', self::POLITICA_CONTENIDO), false);
        }

        if ($request->isSecure() && app()->isProduction()) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        return $respuesta;
    }

    /**
     * Solo para páginas HTML. No se aplica a los PDF, ni con "npm run dev" (Vite sirve desde otro puerto),
     * ni a la página de error detallada de Laravel en modo depuración (usa scripts en línea).
     */
    private function aplicaPoliticaDeContenido(Response $respuesta): bool
    {
        return str_contains((string) $respuesta->headers->get('Content-Type'), 'text/html')
            && ! file_exists(public_path('hot'))
            && ! (config('app.debug') && $respuesta->getStatusCode() >= 500);
    }
}
