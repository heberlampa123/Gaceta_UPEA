<?php

namespace App\Http\Resources;

use App\Models\Documento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Datos públicos de un documento publicado (portal y API JSON).
 * Nunca incluye datos internos: rutas de archivos, usuarios, estados ni historial.
 *
 * @mixin Documento
 */
class DocumentoPublicoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_documento,
            'tipo' => $this->tipoDocumental->nombre,
            'numero' => $this->numero,
            'gestion' => $this->gestion,
            'titulo' => $this->titulo,
            'resumen' => Str::limit((string) $this->descripcion, 220),
            'instancia_emisora' => $this->instancia_emisora,
            'fecha_emision' => $this->fecha_emision->format('Y-m-d'),
            'fecha_emision_texto' => $this->fecha_emision->format('d/m/Y'),
            'fecha_publicacion_texto' => $this->fecha_publicacion?->format('d/m/Y'),
            'url' => route('portal.documentos.show', $this->id_documento),
            'url_pdf' => route('portal.documentos.pdf', $this->id_documento),
            'url_descarga' => route('portal.documentos.pdf', [$this->id_documento, 'descargar' => 1]),
        ];
    }
}
