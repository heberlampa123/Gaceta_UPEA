{{--
    Un resultado de búsqueda. Se usa de dos formas, con el mismo diseño:
      - Desde el servidor, con los datos de $d (DocumentoPublicoResource).
      - Como <template> vacío ($d = []), que resources/js/portal.js llena con la respuesta de Axios
        usando los atributos data-campo (texto) y data-enlace (href).
--}}
<article class="card border-0 shadow-sm mb-3" data-resultado>
    <div class="card-body">
        <div class="small text-body-secondary mb-1">
            <span class="badge text-bg-light border" data-campo="tipo">{{ $d['tipo'] ?? '' }}</span>
            · Gestión <span data-campo="gestion">{{ $d['gestion'] ?? '' }}</span>
        </div>
        <h3 class="h5 mb-2">
            <a href="{{ $d['url'] ?? '#' }}" data-enlace="url" class="text-decoration-none link-dark">
                N.º <span data-campo="numero" class="font-monospace">{{ $d['numero'] ?? '' }}</span> — <span data-campo="titulo">{{ $d['titulo'] ?? '' }}</span>
            </a>
        </h3>
        <p class="mb-2 text-body-secondary" data-campo="resumen" @if (($d['resumen'] ?? '') === '') hidden @endif>{{ $d['resumen'] ?? '' }}</p>
        <div class="small text-body-secondary">
            <i class="bi bi-calendar3"></i> Emitida el <span data-campo="fecha_emision_texto">{{ $d['fecha_emision_texto'] ?? '' }}</span>
            · <i class="bi bi-bank"></i> <span data-campo="instancia_emisora">{{ $d['instancia_emisora'] ?? '' }}</span>
        </div>
        <div class="mt-3 d-flex flex-wrap gap-2">
            <a href="{{ $d['url'] ?? '#' }}" data-enlace="url" class="btn btn-sm btn-gaceta"><i class="bi bi-eye me-1"></i> Ver documento</a>
            <a href="{{ $d['url_descarga'] ?? '#' }}" data-enlace="url_descarga" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i> Descargar PDF</a>
        </div>
    </div>
</article>
