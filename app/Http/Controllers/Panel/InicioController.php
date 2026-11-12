<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\EstadoDocumento;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Inicio del panel: resumen de documentos y actividad reciente (Figura 2). */
class InicioController extends Controller
{
    public function __invoke(Request $request): View
    {
        $usuario = $request->user();

        $estados = $usuario->can('documento.ver')
            ? EstadoDocumento::where('activo', true)
                ->withCount(['documentos' => fn ($q) => $q->where('activo', true)])
                ->orderBy('orden')
                ->get()
            : collect();

        $actividad = Auditoria::where('id_usuario', $usuario->id_usuario)
            ->latest('fecha')
            ->limit(5)
            ->get();

        return view('panel.inicio', compact('usuario', 'estados', 'actividad'));
    }
}
