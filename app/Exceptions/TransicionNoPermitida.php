<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Se lanza cuando se intenta un cambio de estado que el flujo documental no permite
 * (por ejemplo, publicar un documento que no está aprobado, o si otro usuario
 * cambió el estado mientras tanto). Se muestra como mensaje de error al usuario.
 */
class TransicionNoPermitida extends RuntimeException
{
    public function render(Request $request): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $this->getMessage()], 409)
            : back()->with('error', $this->getMessage());
    }
}
