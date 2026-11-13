<?php

namespace App\Listeners;

use App\Events\DocumentoCambioDeEstado;
use App\Models\Documento;
use App\Models\EstadoDocumento;
use App\Models\RelacionDocumental;
use App\Models\SeguimientoDocumento;

/**
 * Relaciones entre normas (13/11/2026): cuando se publica un documento que abroga, deroga o modifica
 * otras normas, se deja constancia en el seguimiento de cada norma afectada
 * (ej.: «Abrogada por Resolución del HCU N.º 045/2026»).
 *
 * Solo registra historial: la vigencia se calcula a partir de las relaciones (Documento::scopeVigentes).
 * Laravel registra este oyente automáticamente por el tipo del parámetro de handle().
 */
class RegistrarEfectoDeRelaciones
{
    public function handle(DocumentoCambioDeEstado $evento): void
    {
        if ($evento->estadoNuevo !== EstadoDocumento::PUBLICADO || ! Documento::relacionesHabilitadas()) {
            return;
        }

        $origen = $evento->documento->loadMissing('tipoDocumental');

        $origen->relacionesEmitidas()->with(['tipo', 'destino.versionVigente'])->get()
            ->each(function (RelacionDocumental $relacion) use ($origen, $evento) {
                $destino = $relacion->destino;

                SeguimientoDocumento::create([
                    'id_documento' => $destino->id_documento,
                    'id_estado_anterior' => $destino->id_estado_actual,
                    'id_estado_nuevo' => $destino->id_estado_actual,
                    'id_version' => $destino->versionVigente?->id_version,
                    'accion' => SeguimientoDocumento::ACCION_AFECTACION,
                    'comentario' => mb_substr(trim("{$relacion->tipo->nombre_inverso} {$origen->nombreCorto()}. ".($relacion->detalle ?? '')), 0, 500),
                    'id_usuario' => $evento->usuario->id_usuario,
                ]);
            });
    }
}
