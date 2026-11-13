<?php

namespace App\Events;

use App\Models\Documento;
use App\Models\Usuario;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se emite después de cada cambio de estado de un documento (envío, devolución, reenvío,
 * aprobación y publicación). En la Fase 7 lo escucha el módulo de notificaciones (RF-08).
 */
class DocumentoCambioDeEstado
{
    use Dispatchable;

    public function __construct(
        public readonly Documento $documento,
        public readonly string $estadoAnterior,
        public readonly string $estadoNuevo,
        public readonly string $accion,
        public readonly Usuario $usuario,
    ) {}
}
