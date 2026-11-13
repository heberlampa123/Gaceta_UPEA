<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Models\EstadoDocumento;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bandeja del Revisor / Publicador (Figura 2: «Revisión y publicación»).
 * No muestra los documentos que el propio usuario registró, porque no puede revisarlos.
 */
class RevisionController extends Controller
{
    public const BANDEJAS = [
        'pendientes' => ['Pendientes de revisión', EstadoDocumento::PENDIENTE_REVISION],
        'aprobados' => ['Aprobados por publicar', EstadoDocumento::APROBADO],
        'observados' => ['Devueltos con observaciones', EstadoDocumento::OBSERVADO],
    ];

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $bandeja = array_key_exists($request->query('bandeja'), self::BANDEJAS) ? $request->query('bandeja') : 'pendientes';

        $base = fn (string $estado) => Documento::query()
            ->activos()
            ->enEstado($estado)
            ->where('id_usuario_registro', '<>', $usuario->id_usuario);

        $conteos = collect(self::BANDEJAS)->map(fn ($b) => $base($b[1])->count());

        $documentos = $base(self::BANDEJAS[$bandeja][1])
            ->with(['tipoDocumental', 'estadoActual', 'usuarioRegistro', 'versionVigente'])
            ->withCount(['observaciones as observaciones_pendientes_count' => fn ($q) => $q->whereNull('fecha_atencion')])
            ->orderBy('fecha_actualizacion') // los más antiguos primero
            ->paginate(15)
            ->withQueryString();

        return view('panel.revision.index', compact('bandeja', 'conteos', 'documentos'));
    }
}
