<?php

namespace App\Http\Resources;

use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Notificación para la campana del panel (API consumida con Axios).
 *
 * @mixin Notificacion
 */
class NotificacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_notificacion,
            'tipo' => $this->tipo,
            'icono' => $this->icono(),
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'fecha_texto' => $this->fecha_creacion->diffForHumans(),
            'fecha' => $this->fecha_creacion->toIso8601String(),
            'leida' => $this->fecha_lectura !== null,
            // Abre el documento y marca la notificación como leída.
            'url' => route('panel.notificaciones.abrir', $this->id_notificacion),
        ];
    }
}
