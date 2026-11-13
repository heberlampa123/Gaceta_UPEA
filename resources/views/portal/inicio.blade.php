@extends('layouts.portal')

@section('titulo', 'Documentos oficiales')

@section('contenido')
    {{--
        Un solo formulario envuelve el buscador, los filtros y el orden: funciona sin JavaScript
        (envío normal) y, con JavaScript, resources/js/portal.js actualiza los resultados con Axios.
    --}}
    <form method="GET" action="{{ route('portal.inicio') }}" data-portal-busqueda data-api="{{ route('api.documentos.index') }}">
        {{-- Bienvenida: breve descripción sobre la foto del edificio emblemático de la UPEA --}}
        <section class="portal-bienvenida">
            <img src="{{ asset('img/upea-emblematico-hd.jpg') }}" alt="" class="portal-bienvenida-foto">
            <div class="container position-relative py-5">
                <div class="py-lg-4" style="max-width: 40rem">
                    <span class="badge rounded-pill portal-bienvenida-etiqueta mb-3">Universidad Pública de El Alto</span>
                    <h1 class="display-5 fw-bold mb-3">Gaceta Universitaria</h1>
                    <p class="lead mb-3">
                        Medio oficial de publicación de la UPEA. Aquí puede consultar, ver y descargar las resoluciones
                        del Honorable Consejo Universitario, revisadas y aprobadas antes de su publicación.
                    </p>
                    <p class="mb-0 small portal-bienvenida-dato">
                        <i class="bi bi-file-earmark-check me-1"></i>
                        <strong>{{ number_format($totalPublicados, 0, ',', '.') }}</strong> documento(s) vigentes · Consulta libre, sin crear una cuenta
                    </p>
                </div>
            </div>
        </section>

        <div class="container py-4" id="resultados">
            <div class="row g-4">
                {{-- Buscador y filtros --}}
                <aside class="col-lg-4 col-xl-3">
                    <div class="card border-0 shadow-sm sticky-lg-top" style="top: 1rem; z-index: 1">
                        <div class="card-body p-4">
                            <h2 class="h5 mb-3">Consulta de documentos</h2>

                            <div class="mb-4" role="search">
                                <label for="q" class="form-label fw-medium">Buscar</label>
                                <div class="input-group">
                                    <input type="search" id="q" name="q" value="{{ $filtros['q'] ?? '' }}" maxlength="100" class="form-control"
                                           placeholder="Número, título o palabra clave" autocomplete="off">
                                    <button class="btn btn-gaceta" type="submit" aria-label="Buscar"><i class="bi bi-search"></i></button>
                                </div>
                            </div>

                            <h3 class="small text-uppercase fw-semibold text-body-secondary mb-3" style="letter-spacing: .06em">Filtros</h3>

                            <div class="mb-3">
                                <label for="tipo" class="form-label fw-medium">Tipo documental</label>
                                <select id="tipo" name="tipo" class="form-select">
                                    <option value="">Todos</option>
                                    @foreach ($opciones['tipos'] as $tipo)
                                        <option value="{{ $tipo->id_tipo_documental }}" @selected(($filtros['tipo'] ?? null) === $tipo->id_tipo_documental)>{{ $tipo->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="gestion" class="form-label fw-medium">Gestión</label>
                                <select id="gestion" name="gestion" class="form-select">
                                    <option value="">Todas</option>
                                    @foreach ($opciones['gestiones'] as $gestion)
                                        <option value="{{ $gestion }}" @selected(($filtros['gestion'] ?? null) === $gestion)>{{ $gestion }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="numero" class="form-label fw-medium">Número de resolución</label>
                                <input type="text" id="numero" name="numero" value="{{ $filtros['numero'] ?? '' }}" maxlength="30" class="form-control" placeholder="Ej.: 045/2026">
                            </div>

                            <fieldset class="mb-3">
                                <legend class="form-label fw-medium fs-6">Fecha de emisión</legend>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label for="desde" class="form-label small text-body-secondary mb-1">Desde</label>
                                        <input type="date" id="desde" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-6">
                                        <label for="hasta" class="form-label small text-body-secondary mb-1">Hasta</label>
                                        <input type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="form-control form-control-sm">
                                    </div>
                                </div>
                            </fieldset>

                            @if ($opciones['instancias']->count() > 1)
                                <div class="mb-3">
                                    <label for="instancia" class="form-label fw-medium">Instancia emisora</label>
                                    <select id="instancia" name="instancia" class="form-select">
                                        <option value="">Todas</option>
                                        @foreach ($opciones['instancias'] as $instancia)
                                            <option value="{{ $instancia }}" @selected(($filtros['instancia'] ?? null) === $instancia)>{{ $instancia }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="d-flex gap-2 pt-1">
                                <button type="submit" class="btn btn-gaceta flex-fill">Aplicar filtros</button>
                                <a href="{{ route('portal.inicio') }}" class="btn btn-outline-secondary" data-limpiar-filtros>Limpiar</a>
                            </div>
                        </div>
                    </div>
                </aside>

                {{-- Resultados --}}
                <section class="col-lg-8 col-xl-9" aria-labelledby="tituloResultados">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <h2 id="tituloResultados" class="visually-hidden">Resultados</h2>
                        <span class="fw-semibold" data-total aria-live="polite">{{ $resultados->total() }} documento(s) encontrado(s)</span>
                        <div class="spinner-border spinner-border-sm text-secondary" role="status" data-cargando hidden>
                            <span class="visually-hidden">Buscando…</span>
                        </div>
                        <div class="ms-auto d-flex align-items-center gap-2">
                            <label for="orden" class="small text-body-secondary text-nowrap">Ordenar:</label>
                            <select id="orden" name="orden" class="form-select form-select-sm">
                                @foreach ($opciones['ordenes'] as $valor => $texto)
                                    <option value="{{ $valor }}" @selected($filtros['orden'] === $valor)>{{ $texto }}</option>
                                @endforeach
                            </select>
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
                    <nav class="d-flex align-items-center justify-content-center gap-3 mt-4" aria-label="Paginación de resultados" data-paginacion
                         @if ($resultados->lastPage() <= 1) hidden @endif>
                        <a href="{{ $resultados->previousPageUrl() ?? '#' }}" data-pagina="{{ max(1, $resultados->currentPage() - 1) }}" data-anterior aria-label="Página anterior"
                           @class(['btn btn-outline-secondary', 'disabled' => $resultados->onFirstPage()])><i class="bi bi-chevron-left"></i></a>
                        <span class="small text-body-secondary" data-pagina-texto>Página {{ $resultados->currentPage() }} de {{ $resultados->lastPage() }}</span>
                        <a href="{{ $resultados->nextPageUrl() ?? '#' }}" data-pagina="{{ min($resultados->lastPage(), $resultados->currentPage() + 1) }}" data-siguiente aria-label="Página siguiente"
                           @class(['btn btn-outline-secondary', 'disabled' => ! $resultados->hasMorePages()])><i class="bi bi-chevron-right"></i></a>
                    </nav>

                    <template id="plantillaResultado">
                        @include('portal._resultado', ['d' => []])
                    </template>
                </section>
            </div>
        </div>
    </form>
@endsection
