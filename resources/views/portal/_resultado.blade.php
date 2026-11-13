{{--
    Un resultado de búsqueda. Se usa de dos formas, con el mismo diseño:
      - Desde el servidor, con los datos de $d (DocumentoPublicoResource).
      - Como <template> vacío ($d = []), que resources/js/portal.js llena con la respuesta de Axios
        usando los atributos data-campo (texto) y data-enlace (href).
--}}
<article class="card resultado border-0 shadow-sm mb-3" data-resultado>
    <div class="card-body d-flex flex-column flex-md-row gap-3 align-items-md-center p-3 p-md-4">
        <div class="d-flex gap-3 flex-grow-1 min-w-0">
            <div class="icono-pdf" aria-hidden="true">PDF</div>
            <div class="min-w-0">
                <h3 class="h5 mb-1">
                    <a href="{{ $d['url'] ?? '#' }}" data-enlace="url" class="link-body-emphasis">
                        <span data-campo="tipo">{{ $d['tipo'] ?? '' }}</span> N.º <span data-campo="numero">{{ $d['numero'] ?? '' }}</span>
                    </a>
                </h3>
                <div class="small text-body-secondary mb-1">
                    Gestión <span data-campo="gestion">{{ $d['gestion'] ?? '' }}</span>
                    · Emitida el <span data-campo="fecha_emision_texto">{{ $d['fecha_emision_texto'] ?? '' }}</span>
                    · <span data-campo="instancia_emisora">{{ $d['instancia_emisora'] ?? '' }}</span>
                </div>
                <p class="mb-1 fw-medium" data-campo="titulo">{{ $d['titulo'] ?? '' }}</p>
                <p class="mb-0 small text-body-secondary" data-campo="resumen" @if (($d['resumen'] ?? '') === '') hidden @endif>{{ $d['resumen'] ?? '' }}</p>
            </div>
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
            <a href="{{ $d['url'] ?? '#' }}" data-enlace="url" class="btn btn-outline-secondary"><i class="bi bi-eye me-1"></i> Ver</a>
            <a href="{{ $d['url_descarga'] ?? '#' }}" data-enlace="url_descarga" class="btn btn-gaceta"><i class="bi bi-download me-1"></i> Descargar PDF</a>
        </div>
    </div>
</article>
