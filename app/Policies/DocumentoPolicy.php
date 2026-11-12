<?php

namespace App\Policies;

use App\Models\Documento;
use App\Models\Usuario;
use Illuminate\Auth\Access\Response;

/**
 * Reglas de autorización sobre un documento concreto: además del permiso,
 * el documento debe estar en un estado que admita cambios (Borrador u Observado).
 */
class DocumentoPolicy
{
    public function update(Usuario $usuario, Documento $documento): Response
    {
        return $this->editable($usuario, $documento);
    }

    public function agregarVersion(Usuario $usuario, Documento $documento): Response
    {
        return $this->editable($usuario, $documento);
    }

    private function editable(Usuario $usuario, Documento $documento): Response
    {
        if (! $usuario->tienePermiso('documento.editar')) {
            return Response::deny('No tiene permiso para modificar documentos.');
        }

        return $documento->esEditable()
            ? Response::allow()
            : Response::deny('Solo se pueden modificar documentos en estado Borrador u Observado.');
    }
}
