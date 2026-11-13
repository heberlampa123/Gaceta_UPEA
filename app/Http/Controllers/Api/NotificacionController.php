<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificacionResource;
use App\Models\Notificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de notificaciones del usuario autenticado (RF-08), protegida con Sanctum.
 * La consume la campana del panel con Axios para actualizarse sin recargar la página.
 * Cada usuario solo ve y modifica sus propias notificaciones.
 */
class NotificacionController extends Controller
{
    public const RECIENTES = 6;

    /** GET /api/notificaciones/resumen → contador de no leídas y las más recientes. */
    public function resumen(Request $request): JsonResponse
    {
        $usuario = $request->user();

        return response()->json([
            'no_leidas' => $usuario->notificaciones()->noLeidas()->count(),
            'recientes' => NotificacionResource::collection(
                $usuario->notificaciones()->latest('fecha_creacion')->latest('id_notificacion')->limit(self::RECIENTES)->get()
            )->resolve($request),
        ]);
    }

    /** PATCH /api/notificaciones/{id}/leer */
    public function leer(Request $request, Notificacion $notificacion): JsonResponse
    {
        abort_unless($notificacion->id_usuario_destino === $request->user()->id_usuario, 404);

        $notificacion->marcarComoLeida();

        return response()->json(['no_leidas' => $request->user()->notificaciones()->noLeidas()->count()]);
    }

    /** POST /api/notificaciones/leer-todas */
    public function leerTodas(Request $request): JsonResponse
    {
        $request->user()->notificaciones()->noLeidas()->update(['fecha_lectura' => now()]);

        return response()->json(['no_leidas' => 0]);
    }
}
