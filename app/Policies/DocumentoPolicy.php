<?php

namespace App\Policies;

use App\Models\Documento;
use App\Models\EstadoDocumento;
use App\Models\Usuario;
use Illuminate\Auth\Access\Response;

/**
 * Reglas de autorización sobre un documento concreto. Además del permiso, cada acción
 * exige que el documento esté en el estado correspondiente del flujo (4.4.10).
 * Quien registró el documento no puede revisarlo, aprobarlo ni publicarlo.
 */
class DocumentoPolicy
{
    // ----------------------------------------------------------------- Edición (Fase 3)

    public function update(Usuario $usuario, Documento $documento): Response
    {
        return $this->editable($usuario, $documento);
    }

    public function agregarVersion(Usuario $usuario, Documento $documento): Response
    {
        return $this->editable($usuario, $documento);
    }

    // ----------------------------------------------------------------- Flujo (Fase 5)

    public function enviarARevision(Usuario $usuario, Documento $documento): Response
    {
        return $this->verificar($usuario, $documento, 'documento.enviar_revision', EstadoDocumento::BORRADOR);
    }

    public function atenderObservaciones(Usuario $usuario, Documento $documento): Response
    {
        return $this->verificar($usuario, $documento, 'observacion.atender', EstadoDocumento::OBSERVADO);
    }

    public function devolver(Usuario $usuario, Documento $documento): Response
    {
        return $this->verificar($usuario, $documento, 'documento.revisar', EstadoDocumento::PENDIENTE_REVISION, revisorDistinto: true);
    }

    public function aprobar(Usuario $usuario, Documento $documento): Response
    {
        return $this->verificar($usuario, $documento, 'documento.aprobar', EstadoDocumento::PENDIENTE_REVISION, revisorDistinto: true);
    }

    public function publicar(Usuario $usuario, Documento $documento): Response
    {
        return $this->verificar($usuario, $documento, 'documento.publicar', EstadoDocumento::APROBADO, revisorDistinto: true);
    }

    // ----------------------------------------------------------------- Internos

    private function editable(Usuario $usuario, Documento $documento): Response
    {
        if (! $usuario->tienePermiso('documento.editar')) {
            return Response::deny('No tiene permiso para modificar documentos.');
        }

        return $documento->esEditable()
            ? Response::allow()
            : Response::deny('Solo se pueden modificar documentos en estado Borrador u Observado.');
    }

    private function verificar(Usuario $usuario, Documento $documento, string $permiso, string $estado, bool $revisorDistinto = false): Response
    {
        if (! $usuario->tienePermiso($permiso)) {
            return Response::deny('No tiene permiso para realizar esta acción.');
        }

        if (! $documento->activo || $documento->estadoActual?->codigo !== $estado) {
            return Response::deny('La acción no está permitida en el estado actual del documento.');
        }

        if ($revisorDistinto && $documento->id_usuario_registro === $usuario->id_usuario) {
            return Response::deny('No puede revisar, aprobar ni publicar un documento que usted registró.');
        }

        return Response::allow();
    }
}
