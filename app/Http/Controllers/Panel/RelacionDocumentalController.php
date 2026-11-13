<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Models\RelacionDocumental;
use App\Services\RelacionesDocumentales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Relaciones entre normas desde la ficha del documento (13/11/2026).
 * Se gestionan con el mismo permiso y en los mismos estados que «Editar datos» (DocumentoPolicy@update).
 */
class RelacionDocumentalController extends Controller
{
    public function __construct(private readonly RelacionesDocumentales $relaciones) {}

    public function store(Request $request, Documento $documento): RedirectResponse
    {
        $this->autorizar($documento);

        $datos = $request->validate([
            'id_documento_destino' => ['required', 'integer'],
            'id_tipo_relacion' => ['required', 'integer'],
            'detalle' => ['nullable', 'string', 'max:300'],
        ], [
            'id_documento_destino.required' => 'Busque y seleccione la norma afectada.',
        ], [
            'id_tipo_relacion' => 'tipo de relación',
            'detalle' => 'detalle',
        ]);

        $relacion = $this->relaciones->agregar(
            $documento,
            (int) $datos['id_documento_destino'],
            (int) $datos['id_tipo_relacion'],
            $datos['detalle'] ?? null,
            $request->user(),
        );
        $relacion->load('tipo', 'destino.tipoDocumental');

        return back()->with('exito', "Se registró: {$relacion->tipo->nombre} {$relacion->destino->nombreCorto()}. "
            .'Surtirá efecto cuando este documento se publique.');
    }

    public function destroy(Documento $documento, RelacionDocumental $relacion): RedirectResponse
    {
        abort_unless($relacion->id_documento_origen === $documento->id_documento, 404);
        $this->autorizar($documento);

        $this->relaciones->quitar($relacion);

        return back()->with('exito', 'Se quitó la relación.');
    }

    /** Buscador de normas afectables (JSON, con Axios). */
    public function candidatos(Request $request, Documento $documento): JsonResponse
    {
        $this->autorizar($documento);

        return response()->json([
            'data' => $this->relaciones->candidatos($documento, (string) $request->query('q', ''))
                ->map(fn (Documento $d) => [
                    'id' => $d->id_documento,
                    'nombre' => $d->nombreCorto(),
                    'titulo' => $d->titulo,
                    'fecha_emision' => $d->fecha_emision->format('d/m/Y'),
                ])->values(),
        ]);
    }

    /** Con la función deshabilitada (GACETA_RELACIONES=false) las rutas no existen. */
    private function autorizar(Documento $documento): void
    {
        abort_unless(Documento::relacionesHabilitadas(), 404);
        $this->authorize('update', $documento);
    }
}
