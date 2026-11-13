<?php

namespace App\Listeners;

use App\Events\DocumentoCambioDeEstado;
use App\Models\EstadoDocumento;
use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Support\Collection;

/**
 * Notificaciones internas sobre el flujo documental (RF-08, Ref. 25: generación de notificaciones).
 *
 * Destinatarios según el nuevo estado del documento:
 *   Pendiente de revisión → revisores activos (aviso de documento nuevo o corregido por revisar)
 *   Observado             → quien registró el documento
 *   Aprobado              → quien registró el documento
 *   Publicado             → quien registró el documento
 *
 * Nunca se notifica a quien realizó la acción, ni a un revisor sobre un documento que él registró.
 * Laravel registra este oyente automáticamente por el tipo del parámetro de handle().
 */
class NotificarCambioDeEstado
{
    public function handle(DocumentoCambioDeEstado $evento): void
    {
        $documento = $evento->documento->loadMissing('tipoDocumental', 'usuarioRegistro');
        $nombre = $documento->nombreCorto();
        $actor = $evento->usuario->nombre_completo;

        [$tipo, $titulo, $mensaje, $destinatarios] = match ($evento->estadoNuevo) {
            EstadoDocumento::PENDIENTE_REVISION => [
                Notificacion::NUEVO_PENDIENTE,
                $evento->estadoAnterior === EstadoDocumento::OBSERVADO ? 'Documento corregido por revisar' : 'Nuevo documento por revisar',
                "{$nombre}: {$documento->titulo}. Enviado por {$actor}.",
                Usuario::conPermiso('documento.revisar')->whereKeyNot($documento->id_usuario_registro)->get(),
            ],
            EstadoDocumento::OBSERVADO => [
                Notificacion::OBSERVADO,
                'Su documento fue observado',
                "{$nombre} fue devuelto con observaciones por {$actor}. Atiéndalas y reenvíelo a revisión.",
                $this->registrador($documento->usuarioRegistro),
            ],
            EstadoDocumento::APROBADO => [
                Notificacion::APROBADO,
                'Su documento fue aprobado',
                "{$nombre} fue autorizado para su publicación por {$actor}.",
                $this->registrador($documento->usuarioRegistro),
            ],
            EstadoDocumento::PUBLICADO => [
                Notificacion::PUBLICADO,
                'Su documento fue publicado',
                "{$nombre} ya está disponible en el portal público.",
                $this->registrador($documento->usuarioRegistro),
            ],
            default => [null, null, null, collect()],
        };

        $destinatarios
            ->reject(fn (Usuario $usuario) => $usuario->id_usuario === $evento->usuario->id_usuario)
            ->each(fn (Usuario $usuario) => Notificacion::create([
                'id_usuario_destino' => $usuario->id_usuario,
                'id_documento' => $documento->id_documento,
                'tipo' => $tipo,
                'titulo' => $titulo,
                'mensaje' => mb_substr($mensaje, 0, 500),
            ]));
    }

    /** @return Collection<int, Usuario> */
    private function registrador(?Usuario $usuario): Collection
    {
        return collect([$usuario])->filter(fn (?Usuario $u) => $u?->activo);
    }
}
