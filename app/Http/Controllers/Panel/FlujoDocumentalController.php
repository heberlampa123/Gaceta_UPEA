<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Services\FlujoDocumental;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Acciones del flujo de revisión y publicación (RF-03, Ref. 15–20 y 33).
 * La autorización (permiso + estado + revisor distinto) está en DocumentoPolicy;
 * la lógica de cada transición, en el servicio FlujoDocumental.
 */
class FlujoDocumentalController extends Controller
{
    private const REGLA_COMENTARIO = ['nullable', 'string', 'max:300'];

    public function __construct(private readonly FlujoDocumental $flujo) {}

    /** Encargado: Borrador → Pendiente de revisión. */
    public function enviar(Request $request, Documento $documento): RedirectResponse
    {
        $this->autorizarEnFlujo('enviarARevision', $documento);
        $datos = $request->validate(['comentario' => self::REGLA_COMENTARIO]);

        $this->flujo->enviarARevision($documento, $request->user(), $datos['comentario'] ?? null);

        return redirect()->route('panel.documentos.show', $documento)->with('exito', 'El documento se envió a revisión.');
    }

    /** Encargado: atiende las observaciones y reenvía (Observado → Pendiente de revisión). */
    public function reenviar(Request $request, Documento $documento): RedirectResponse
    {
        $this->autorizarEnFlujo('atenderObservaciones', $documento);
        $datos = $request->validate([
            'respuestas' => ['nullable', 'array'],
            'respuestas.*' => ['nullable', 'string', 'max:2000'],
            'comentario' => self::REGLA_COMENTARIO,
        ]);

        $this->flujo->reenviar($documento, $request->user(), $datos['respuestas'] ?? [], $datos['comentario'] ?? null);

        return redirect()->route('panel.documentos.show', $documento)->with('exito', 'Se atendieron las observaciones y el documento volvió a revisión.');
    }

    /** Revisor: Pendiente de revisión → Observado. */
    public function devolver(Request $request, Documento $documento): RedirectResponse
    {
        $this->autorizarEnFlujo('devolver', $documento);
        $datos = $request->validate([
            'observaciones' => ['required', 'array', 'max:20'],
            'observaciones.*' => ['nullable', 'string', 'max:2000'],
            'comentario' => self::REGLA_COMENTARIO,
        ], ['observaciones.required' => 'Registre al menos una observación para devolver el documento.']);

        $this->flujo->devolver($documento, $request->user(), $datos['observaciones'], $datos['comentario'] ?? null);

        return redirect()->route('panel.revision.index')->with('exito', "Se devolvió {$documento->nombreCorto()} con observaciones.");
    }

    /** Revisor: Pendiente de revisión → Aprobado para publicación. */
    public function aprobar(Request $request, Documento $documento): RedirectResponse
    {
        $this->autorizarEnFlujo('aprobar', $documento);
        $datos = $request->validate(['comentario' => self::REGLA_COMENTARIO]);

        $this->flujo->aprobar($documento, $request->user(), $datos['comentario'] ?? null);

        return redirect()->route('panel.revision.index', ['bandeja' => 'aprobados'])
            ->with('exito', "Se aprobó {$documento->nombreCorto()} para su publicación.");
    }

    /** Revisor / Publicador: Aprobado → Publicado (visible en el portal). */
    public function publicar(Request $request, Documento $documento): RedirectResponse
    {
        $this->autorizarEnFlujo('publicar', $documento);
        $datos = $request->validate(['comentario' => self::REGLA_COMENTARIO]);

        $this->flujo->publicar($documento, $request->user(), $datos['comentario'] ?? null);

        return redirect()->route('panel.documentos.show', $documento)
            ->with('exito', "{$documento->nombreCorto()} fue publicado y ya está disponible en el portal.");
    }
}
