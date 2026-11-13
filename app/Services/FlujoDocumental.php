<?php

namespace App\Services;

use App\Events\DocumentoCambioDeEstado;
use App\Exceptions\TransicionNoPermitida;
use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\EstadoDocumento;
use App\Models\Observacion;
use App\Models\SeguimientoDocumento;
use App\Models\Usuario;
use App\Models\VersionDocumento;
use App\Support\Auditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Flujo de revisión y publicación (RF-03, RF-07; sección 4.4.10 del análisis).
 *
 * Transiciones permitidas (cualquier otra se rechaza):
 *   Borrador              → Pendiente de revisión   (Encargado envía a revisión)
 *   Pendiente de revisión → Observado               (Revisor devuelve con observaciones)
 *   Observado             → Pendiente de revisión   (Encargado atiende las observaciones y reenvía)
 *   Pendiente de revisión → Aprobado                (Revisor autoriza la publicación)
 *   Aprobado              → Publicado               (Revisor publica en el portal)
 *
 * Cada transición, en una sola transacción: bloquea el documento, verifica su estado actual,
 * cambia el estado, registra el seguimiento y la auditoría. Después emite DocumentoCambioDeEstado.
 * Quien registró un documento no puede revisarlo, aprobarlo ni publicarlo.
 */
class FlujoDocumental
{
    /** accion => [estado de origen, estado de destino] */
    public const TRANSICIONES = [
        'enviar' => [EstadoDocumento::BORRADOR, EstadoDocumento::PENDIENTE_REVISION],
        'devolver' => [EstadoDocumento::PENDIENTE_REVISION, EstadoDocumento::OBSERVADO],
        'reenviar' => [EstadoDocumento::OBSERVADO, EstadoDocumento::PENDIENTE_REVISION],
        'aprobar' => [EstadoDocumento::PENDIENTE_REVISION, EstadoDocumento::APROBADO],
        'publicar' => [EstadoDocumento::APROBADO, EstadoDocumento::PUBLICADO],
    ];

    public function enviarARevision(Documento $documento, Usuario $usuario, ?string $comentario = null): Documento
    {
        return $this->ejecutar($documento, $usuario, 'enviar',
            accionSeguimiento: SeguimientoDocumento::ACCION_ENVIO_REVISION,
            accionAuditoria: Auditoria::ENVIAR_REVISION,
            comentario: $this->unir($comentario, 'Se envió el documento a revisión.'),
        );
    }

    /** @param  string[]  $observaciones  una o más observaciones sobre la versión vigente */
    public function devolver(Documento $documento, Usuario $usuario, array $observaciones, ?string $comentario = null): Documento
    {
        $observaciones = array_values(array_filter(array_map(fn ($o) => trim((string) $o), $observaciones)));

        if ($observaciones === []) {
            throw ValidationException::withMessages(['observaciones' => 'Registre al menos una observación para devolver el documento.']);
        }

        $cantidad = count($observaciones);

        return $this->ejecutar($documento, $usuario, 'devolver',
            accionSeguimiento: SeguimientoDocumento::ACCION_DEVOLUCION,
            accionAuditoria: Auditoria::DEVOLVER,
            comentario: $this->unir($comentario, $cantidad === 1 ? 'Se registró 1 observación.' : "Se registraron {$cantidad} observaciones."),
            revisorDistinto: true,
            antes: function (Documento $doc, VersionDocumento $vigente) use ($observaciones, $usuario) {
                foreach ($observaciones as $texto) {
                    $observacion = Observacion::create([
                        'id_version' => $vigente->id_version,
                        'id_usuario_revisor' => $usuario->id_usuario,
                        'descripcion' => $texto,
                    ]);
                    Auditor::creacion($observacion);
                }
            },
        );
    }

    /**
     * El Encargado responde todas las observaciones pendientes y reenvía el documento a revisión.
     *
     * @param  array<int, string>  $respuestas  id_observacion => respuesta
     */
    public function reenviar(Documento $documento, Usuario $usuario, array $respuestas, ?string $comentario = null): Documento
    {
        return $this->ejecutar($documento, $usuario, 'reenviar',
            accionSeguimiento: SeguimientoDocumento::ACCION_ENVIO_REVISION,
            accionAuditoria: Auditoria::ENVIAR_REVISION,
            comentario: $this->unir($comentario, 'Se atendieron las observaciones y se reenvió a revisión.'),
            antes: function (Documento $doc) use ($respuestas, $usuario) {
                $pendientes = $doc->observaciones()->pendientes()->get();
                $faltantes = [];

                foreach ($pendientes as $observacion) {
                    if (trim((string) ($respuestas[$observacion->id_observacion] ?? '')) === '') {
                        $faltantes["respuestas.{$observacion->id_observacion}"] = 'Describa cómo se atendió esta observación.';
                    }
                }

                if ($faltantes !== []) {
                    throw ValidationException::withMessages($faltantes);
                }

                foreach ($pendientes as $observacion) {
                    $observacion->update([
                        'respuesta' => trim($respuestas[$observacion->id_observacion]),
                        'id_usuario_atiende' => $usuario->id_usuario,
                        'fecha_atencion' => now(),
                    ]);
                    Auditor::modificacion($observacion);
                }
            },
        );
    }

    public function aprobar(Documento $documento, Usuario $usuario, ?string $comentario = null): Documento
    {
        return $this->ejecutar($documento, $usuario, 'aprobar',
            accionSeguimiento: SeguimientoDocumento::ACCION_APROBACION,
            accionAuditoria: Auditoria::APROBAR,
            comentario: $this->unir($comentario, 'El documento fue autorizado para su publicación.'),
            revisorDistinto: true,
            antes: function (Documento $doc) {
                if ($doc->observaciones()->pendientes()->exists()) {
                    throw new TransicionNoPermitida('El documento tiene observaciones pendientes de atención.');
                }
            },
        );
    }

    public function publicar(Documento $documento, Usuario $usuario, ?string $comentario = null): Documento
    {
        return $this->ejecutar($documento, $usuario, 'publicar',
            accionSeguimiento: SeguimientoDocumento::ACCION_PUBLICACION,
            accionAuditoria: Auditoria::PUBLICAR,
            comentario: $this->unir($comentario, 'El documento quedó disponible en el portal público.'),
            revisorDistinto: true,
            antes: function (Documento $doc) {
                $doc->fecha_publicacion = now();
            },
        );
    }

    // ----------------------------------------------------------------- Interno

    private function ejecutar(
        Documento $documento,
        Usuario $usuario,
        string $transicion,
        string $accionSeguimiento,
        string $accionAuditoria,
        string $comentario,
        bool $revisorDistinto = false,
        ?callable $antes = null,
    ): Documento {
        [$desde, $hacia] = self::TRANSICIONES[$transicion];

        $actualizado = DB::transaction(function () use ($documento, $usuario, $desde, $hacia, $accionSeguimiento, $accionAuditoria, $comentario, $revisorDistinto, $antes) {
            // Bloquea el documento: si dos usuarios actúan a la vez, el segundo ve el estado ya cambiado.
            $doc = Documento::whereKey($documento->id_documento)->lockForUpdate()->firstOrFail();
            $estadoActual = EstadoDocumento::whereKey($doc->id_estado_actual)->value('codigo');

            if (! $doc->activo) {
                throw new TransicionNoPermitida('La acción no está permitida: el documento fue anulado.');
            }

            if ($estadoActual !== $desde) {
                throw new TransicionNoPermitida('La acción no está permitida: el documento está en estado «'
                    .EstadoDocumento::where('codigo', $estadoActual)->value('nombre').'».');
            }

            if ($revisorDistinto && $doc->id_usuario_registro === $usuario->id_usuario) {
                throw new TransicionNoPermitida('No puede revisar, aprobar ni publicar un documento que usted registró.');
            }

            // Un documento no se envía ni se aprueba con campos obligatorios de su tipo sin completar.
            // (Devolverlo sí está permitido; publicar no se verifica porque ya se aprobó con los datos exigidos
            // en ese momento y no existe un paso para devolver un documento aprobado.)
            if (in_array($hacia, [EstadoDocumento::PENDIENTE_REVISION, EstadoDocumento::APROBADO], true)
                && ($faltantes = $doc->tipoDocumental->camposObligatoriosFaltantes($doc->metadatos)) !== []) {
                throw new TransicionNoPermitida('Faltan datos obligatorios del tipo «'.$doc->tipoDocumental->nombre.'»: '
                    .implode(', ', $faltantes).'. '.($hacia === EstadoDocumento::APROBADO
                        ? 'Devuelva el documento con una observación para que el encargado los complete.'
                        : 'Complételos con «Editar datos» antes de enviarlo.'));
            }

            $vigente = $doc->versionVigente()->first()
                ?? throw new TransicionNoPermitida('El documento no tiene un archivo PDF vigente.');

            if ($antes) {
                $antes($doc, $vigente);
            }

            $doc->id_estado_actual = EstadoDocumento::idPorCodigo($hacia);
            $doc->save();

            SeguimientoDocumento::create([
                'id_documento' => $doc->id_documento,
                'id_estado_anterior' => EstadoDocumento::idPorCodigo($desde),
                'id_estado_nuevo' => $doc->id_estado_actual,
                'id_version' => $vigente->id_version,
                'accion' => $accionSeguimiento,
                'comentario' => mb_substr($comentario, 0, 500),
                'id_usuario' => $usuario->id_usuario,
            ]);

            Auditor::registrar($accionAuditoria, 'Documento', $doc->id_documento, [
                'estado_anterior' => $desde,
                'estado_nuevo' => $hacia,
                'version' => $vigente->numero_version,
            ]);

            return $doc;
        });

        DocumentoCambioDeEstado::dispatch($actualizado, $desde, $hacia, $accionSeguimiento, $usuario);

        return $actualizado;
    }

    private function unir(?string $comentario, string $predeterminado): string
    {
        $comentario = trim((string) $comentario);

        return $comentario === '' ? $predeterminado : "{$comentario} — {$predeterminado}";
    }
}
