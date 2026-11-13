@extends('layouts.panel')

@section('titulo', 'Panel principal')

@section('contenido')
    <div class="encabezado-pagina">
        <div>
            <h1 class="h3">Panel principal</h1>
            <p>
                Bienvenido(a), {{ $usuario->nombres }} · Resumen de la actividad documental
                @if ($usuario->ultimo_acceso) <span class="d-none d-md-inline">· Último acceso: {{ $usuario->ultimo_acceso->format('d/m/Y H:i') }}</span> @endif
            </p>
        </div>
        <div class="acciones">
            @can('documento.ver')
                <a href="{{ route('panel.documentos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-files me-1"></i> Ver documentos</a>
            @endcan
            @can('documento.registrar')
                <a href="{{ route('panel.documentos.create') }}" class="btn btn-gaceta"><i class="bi bi-file-earmark-plus me-1"></i> Registrar documento</a>
            @endcan
        </div>
    </div>

    {{-- Indicadores interactivos: cada uno abre la lista de documentos filtrada por ese estado --}}
    @if ($indicadores->isNotEmpty())
        <div class="row g-3 mb-4">
            @foreach ($indicadores as [$titulo, $cantidad, $descripcion, $icono, $enlace, $textoEnlace])
                <div class="col-sm-6 col-xl-3">
                    <a href="{{ $enlace }}" class="card indicador border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="icono-cuadro"><i class="bi bi-{{ $icono }}"></i></span>
                            <span>
                                <span class="valor d-block">{{ number_format($cantidad, 0, ',', '.') }}</span>
                                <span class="fw-semibold d-block">{{ $titulo }}</span>
                                <span class="small text-body-secondary">{{ $descripcion }}</span>
                            </span>
                        </div>
                        <div class="card-footer d-flex justify-content-between align-items-center">
                            {{ $textoEnlace }} <i class="bi bi-arrow-right"></i>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Pendientes según el rol --}}
    @if ($pendientes->isNotEmpty())
        <div class="row g-3 mb-4">
            @foreach ($pendientes as [$texto, $cantidad, $enlace, $icono, $color])
                <div class="col-sm-6 col-xl-3">
                    <a href="{{ $enlace }}" class="card indicador border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-center gap-3 py-3">
                            <span class="icono-cuadro bg-{{ $color }}-subtle text-{{ $color }}-emphasis" style="width: 2.5rem; height: 2.5rem; font-size: 1.1rem">
                                <i class="bi bi-{{ $icono }}"></i>
                            </span>
                            <span class="small fw-medium flex-grow-1">{{ $texto }}</span>
                            <span class="fs-4 fw-bold">{{ $cantidad }}</span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Actividad reciente: últimos movimientos documentales --}}
    @can('documento.ver')
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header d-flex flex-wrap align-items-center gap-2 py-3">
                <div>
                    <h2 class="h5 mb-0">Actividad reciente</h2>
                    <small class="text-body-secondary">Últimos registros y movimientos documentales</small>
                </div>
                @can('auditoria.ver')
                    <a href="{{ route('panel.auditoria.index') }}" class="btn btn-sm btn-outline-secondary ms-auto">Ver auditoría</a>
                @endcan
            </div>

            <form method="GET" action="{{ route('panel.inicio') }}" class="card-body border-bottom row g-2 align-items-center">
                <div class="col-md-6 col-xl-3">
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-body-secondary"></i>
                        <input type="search" name="buscar" value="{{ $filtros['buscar'] ?? '' }}" class="form-control ps-5" placeholder="Buscar por título o número…" aria-label="Buscar por título o número">
                    </div>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <select name="gestion" class="form-select" aria-label="Gestión">
                        <option value="">Gestión</option>
                        @foreach ($gestiones as $gestion)
                            <option value="{{ $gestion }}" @selected(($filtros['gestion'] ?? '') == $gestion)>Gestión {{ $gestion }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <select name="tipo" class="form-select" aria-label="Tipo documental">
                        <option value="">Tipo documental</option>
                        @foreach ($tipos as $tipo)
                            <option value="{{ $tipo->id_tipo_documental }}" @selected(($filtros['tipo'] ?? '') == $tipo->id_tipo_documental)>{{ $tipo->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <select name="estado" class="form-select" aria-label="Estado">
                        <option value="">Estado</option>
                        @foreach ($estados as $estado)
                            <option value="{{ $estado->codigo }}" @selected(($filtros['estado'] ?? '') === $estado->codigo)>{{ $estado->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <input type="date" name="fecha" value="{{ $filtros['fecha'] ?? '' }}" class="form-control" aria-label="Fecha del movimiento" title="Fecha del movimiento">
                </div>
                <div class="col-md-4 col-xl-1 d-flex gap-1">
                    <button type="submit" class="btn btn-gaceta flex-fill" aria-label="Filtrar"><i class="bi bi-funnel"></i></button>
                    @if ($filtros)
                        <a href="{{ route('panel.inicio') }}" class="btn btn-outline-secondary" aria-label="Limpiar filtros" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th class="d-none d-lg-table-cell">Tipo documental</th>
                            <th>Responsable</th>
                            <th class="d-none d-md-table-cell">Acción</th>
                            <th>Estado</th>
                            <th class="d-none d-sm-table-cell">Fecha</th>
                            <th class="text-end">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movimientos as $m)
                            <tr>
                                <td>
                                    <div class="fw-semibold text-nowrap">{{ $m->documento->nombreCorto() }}</div>
                                    @if ($m->version)
                                        <small class="text-body-secondary">Versión {{ $m->version->numero_version }} · PDF</small>
                                    @endif
                                </td>
                                <td class="d-none d-lg-table-cell">{{ $m->documento->tipoDocumental->nombre }}</td>
                                <td>
                                    <div>{{ $m->usuario->nombre_completo }}</div>
                                    <small class="text-body-secondary">{{ $m->usuario->roles->pluck('nombre')->join(', ') }}</small>
                                </td>
                                <td class="d-none d-md-table-cell">{{ $m->etiqueta() }}</td>
                                <td>@include('panel.documentos._estado', ['estado' => $m->estadoNuevo])</td>
                                <td class="d-none d-sm-table-cell text-nowrap small">{{ $m->fecha->format('d/m/Y H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('panel.documentos.show', $m->documento) }}" class="btn btn-sm btn-outline-secondary text-nowrap">Ver <i class="bi bi-arrow-right"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-body-secondary py-4">
                                {{ $filtros ? 'No hay movimientos con los filtros indicados.' : 'Aún no hay movimientos documentales.' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endcan

    {{-- Mi actividad reciente (para todos los roles, incluido el administrador) --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-person-check me-1"></i> Mi actividad reciente</h2></div>
        <ul class="list-group list-group-flush">
            @forelse ($actividad as $registro)
                <li class="list-group-item d-flex justify-content-between gap-2 small">
                    <span>{{ $registro->etiqueta() }} @if ($registro->entidad) <span class="text-body-secondary">· {{ $registro->entidad }}</span> @endif</span>
                    <span class="text-body-secondary text-nowrap">{{ $registro->fecha->format('d/m/Y H:i') }}</span>
                </li>
            @empty
                <li class="list-group-item text-body-secondary small">Sin actividad registrada.</li>
            @endforelse
        </ul>
    </div>
@endsection
