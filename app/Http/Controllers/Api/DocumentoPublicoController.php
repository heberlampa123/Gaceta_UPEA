<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentoPublicoResource;
use App\Services\ConsultaPublica;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * API pública de búsqueda (JSON). La consume el portal con Axios para filtrar sin recargar
 * y permitirá futuras integraciones con otros sistemas (4.5.1).
 *
 * GET /api/documentos?q=&tipo=&gestion=&numero=&desde=&hasta=&instancia=&orden=&page=
 */
class DocumentoPublicoController extends Controller
{
    public function __construct(private readonly ConsultaPublica $consulta) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $this->consulta->filtros($request->query());
        $resultados = $this->consulta->buscar($filtros, (int) $request->query('page', 1));

        return DocumentoPublicoResource::collection($resultados)->additional([
            'filtros' => $filtros,
        ]);
    }
}
