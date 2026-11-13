<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentoPublicoResource;
use App\Models\Documento;
use App\Services\ConsultaPublica;
use App\Services\GestionDocumental;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Portal público de la Gaceta (Figura 3; RF-06, Ref. 28–32).
 * Acceso libre, sin iniciar sesión. Solo muestra documentos publicados.
 */
class PortalController extends Controller
{
    public function __construct(private readonly ConsultaPublica $consulta) {}

    /** Buscador con filtros. Muestra los resultados desde el servidor; con JavaScript, Axios los actualiza sin recargar. */
    public function index(Request $request): View
    {
        $filtros = $this->consulta->filtros($request->query());
        $resultados = $this->consulta->buscar($filtros, (int) $request->query('page', 1));

        return view('portal.inicio', [
            'filtros' => $filtros,
            'resultados' => $resultados,
            'documentos' => DocumentoPublicoResource::collection($resultados->getCollection())->resolve($request),
            'opciones' => $this->consulta->opciones(),
            'totalPublicados' => $this->consulta->totalPublicados(),
        ]);
    }

    /** Ficha del documento con su PDF (Ref. 30–31). */
    public function show(Documento $documento): View
    {
        $documento = $this->publicado($documento);
        $documento->load(['tipoDocumental', 'versionVigente']);

        return view('portal.documento', ['documento' => $documento]);
    }

    /** PDF vigente del documento publicado: en el navegador o como descarga (?descargar=1) (Ref. 31–32). */
    public function pdf(Request $request, Documento $documento, GestionDocumental $gestion): StreamedResponse
    {
        $documento = $this->publicado($documento);
        $version = $documento->versionVigente()->firstOrFail();

        $disco = $gestion->disco();
        abort_unless($disco->exists($version->ruta_archivo), 404);

        $nombre = Str::slug($documento->tipoDocumental->codigo.' '.str_replace('/', '-', $documento->numero)).'.pdf';

        return $disco->response(
            $version->ruta_archivo,
            $nombre,
            ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=3600'],
            $request->boolean('descargar') ? 'attachment' : 'inline',
        );
    }

    /** Un documento no publicado (o desactivado) no existe para el visitante. */
    private function publicado(Documento $documento): Documento
    {
        return Documento::publicados()->whereKey($documento->id_documento)->with('tipoDocumental')->firstOr(fn () => abort(404));
    }
}
