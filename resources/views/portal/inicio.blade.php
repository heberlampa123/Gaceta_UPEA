@extends('layouts.portal')

@section('titulo', 'Documentos oficiales')

@section('contenido')
    <div class="mb-4">
        <h1 class="h3 mb-1">Consulta de documentos oficiales</h1>
        <p class="text-body-secondary mb-0">
            Busque, consulte y descargue las resoluciones publicadas por la Universidad Pública de El Alto.
            Hay <strong>{{ number_format($totalPublicados, 0, ',', '.') }}</strong> documento(s) publicados.
        </p>
    </div>

    <div class="row g-4">
        {{-- Buscador y filtros (funcionan sin JavaScript; con JavaScript, Axios actualiza los resultados sin recargar) --}}
        <aside class="col-lg-4 col-xl-3">
            <form method="GET" action="{{ route('portal.inicio') }}" class="card border-0 shadow-sm sticky-lg-top" style="top: 1rem; z-index: 1"
                  data-portal-busqueda data-api="{{ route('api.documentos.index') }}" role="search" aria-label="Buscar documentos">
                <div class="card-body">
                    <label for="q" class="form-label fw-semibold">Buscar</label>
                    <div class="input-group mb-3">
                        <input type="search" id="q" name="q" value="{{ $filtros['q'] ?? '' }}" maxlength="100" class="form-control"
                               placeholder="Número, título o palabras clave" autocomplete="off">
                        <button class="btn btn-gaceta" type="submit" aria-label="Buscar"><i class="bi bi-search"></i></button>
                    </div>

                    <h2 class="h6 text-body-secondary text-uppercase small mb-2">Filtros</h2>

                    <div class="mb-2">
                        <label for="tipo" class="form-label small mb-1">Tipo documental</label>
                        <select id="tipo" name="tipo" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            @foreach ($opciones['tipos'] as $tipo)
                                <option value="{{ $tipo->id_tipo_documental }}" @selected(($filtros['tipo'] ?? null) === $tipo->id_tipo_documental)>{{ $tipo->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label for="gestion" class="form-label small mb-1">Gestión</label>
                            <select id="gestion" name="gestion" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                @foreach ($opciones['gestiones'] as $gestion)
                                    <option value="{{ $gestion }}" @selected(($filtros['gestion'] ?? null) === $gestion)>{{ $gestion }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="numero" class="form-label small mb-1">N.º de resolución</label>
                            <input type="text" id="numero" name="numero" value="{{ $filtros['numero'] ?? '' }}" maxlength="30" class="form-control form-control-sm" placeholder="025/2026">
                        </div>
                    </div>

                    <fieldset class="mb-2">
                        <legend class="form-label small mb-1">Fecha de emisión</legend>
                        <div class="row g-2">
                            <div class="col-6">
                                <label for="desde" class="visually-hidden">Desde</label>
                                <input type="date" id="desde" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="form-control form-control-sm" title="Desde">
                            </div>
                            <div class="col-6">
                                <label for="hasta" class="visually-hidden">Hasta</label>
                                <input type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="form-control form-control-sm" title="Hasta">
                            </div>
                        </div>
                    </fieldset>

                    @if ($opciones['instancias']->count() > 1)
                        <div class="mb-2">
                            <label for="instancia" class="form-label small mb-1">Instancia emisora</label>
                            <select id="instancia" name="instancia" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                @foreach ($opciones['instancias'] as $instancia)
                                    <option value="{{ $instancia }}" @selected(($filtros['instancia'] ?? null) === $instancia)>{{ $instancia }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="orden" class="form-label small mb-1">Ordenar por</label>
                        <select id="orden" name="orden" class="form-select form-select-sm">
                            @foreach ($opciones['ordenes'] as $valor => $texto)
                                <option value="{{ $valor }}" @selected($filtros['orden'] === $valor)>{{ $texto }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-gaceta flex-fill"><i class="bi bi-funnel me-1"></i> Aplicar</button>
                        <a href="{{ route('portal.inicio') }}" class="btn btn-sm btn-outline-secondary" data-limpiar-filtros>Limpiar</a>
                    </div>
                </div>
            </form>
        </aside>

        {{-- Resultados --}}
        <section class="col-lg-8 col-xl-9" aria-labelledby="tituloResultados">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <h2 id="tituloResultados" class="h5 mb-0">Resultados</h2>
                <span class="text-body-secondary small" data-total aria-live="polite">{{ $resultados->total() }} documento(s) encontrado(s)</span>
                <div class="spinner-border spinner-border-sm text-secondary ms-auto" role="status" data-cargando hidden>
                    <span class="visually-hidden">Buscando…</span>
                </div>
            </div>

            <div class="alert alert-danger" role="alert" data-error hidden></div>

            <div data-portal-resultados>
                @foreach ($documentos as $d)
                    @include('portal._resultado', ['d' => $d])
                @endforeach
            </div>

            <div class="card border-0 shadow-sm" data-sin-resultados @if ($resultados->total() > 0) hidden @endif>
                <div class="card-body text-center text-body-secondary py-5">
                    <i class="bi bi-search fs-1 d-block mb-2"></i>
                    No se encontraron documentos con los criterios indicados.
                </div>
            </div>

            {{-- Paginación: enlaces normales sin JavaScript; con JavaScript, portal.js actualiza sus atributos --}}
            <nav class="d-flex align-items-center justify-content-between mt-3" aria-label="Paginación de resultados" data-paginacion
                 @if ($resultados->lastPage() <= 1) hidden @endif>
                <a href="{{ $resultados->previousPageUrl() ?? '#' }}" data-pagina="{{ max(1, $resultados->currentPage() - 1) }}" data-anterior
                   @class(['btn btn-sm btn-outline-secondary', 'disabled' => $resultados->onFirstPage()])><i class="bi bi-chevron-left"></i> Anterior</a>
                <span class="small text-body-secondary" data-pagina-texto>Página {{ $resultados->currentPage() }} de {{ $resultados->lastPage() }}</span>
                <a href="{{ $resultados->nextPageUrl() ?? '#' }}" data-pagina="{{ min($resultados->lastPage(), $resultados->currentPage() + 1) }}" data-siguiente
                   @class(['btn btn-sm btn-outline-secondary', 'disabled' => ! $resultados->hasMorePages()])>Siguiente <i class="bi bi-chevron-right"></i></a>
            </nav>

            <template id="plantillaResultado">
                @include('portal._resultado', ['d' => []])
            </template>
        </section>
    </div>
@endsection
