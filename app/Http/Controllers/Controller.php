<?php

namespace App\Http\Controllers;

use App\Exceptions\TransicionNoPermitida;
use App\Models\Documento;
use App\Policies\DocumentoPolicy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;

abstract class Controller
{
    // Permite $this->authorize('update', $documento) en los controladores.
    use AuthorizesRequests;

    /**
     * Autoriza una acción del flujo documental. Si se niega porque el estado del documento ya cambió
     * (por ejemplo, un doble clic), vuelve a la página anterior con un aviso en lugar de mostrar un 403.
     * Si falta el permiso, responde 403 como siempre (corrección 13/11/2026).
     */
    protected function autorizarEnFlujo(string $habilidad, Documento $documento): void
    {
        $respuesta = Gate::inspect($habilidad, $documento);

        if ($respuesta->allowed()) {
            return;
        }

        if ($respuesta->code() === DocumentoPolicy::CODIGO_ESTADO) {
            throw new TransicionNoPermitida($respuesta->message());
        }

        abort(403, $respuesta->message());
    }
}
