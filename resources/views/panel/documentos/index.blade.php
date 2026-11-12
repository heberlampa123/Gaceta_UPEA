@extends('layouts.panel')

@section('titulo', 'Gestión documental')

@section('contenido')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Gestión documental</h1>
        @can('documento.registrar')
            <a href="{{ route('panel.documentos.create') }}" class="btn btn-gaceta ms-auto">
                <i class="bi bi-file-earmark-plus me-1"></i> Registrar documento
            </a>
        @endcan
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-lg-4">
                <label for="buscar" class="form-label small">Buscar</label>
                <input type="search" id="buscar" name="buscar" value="{{ $filtros['buscar'] ?? '' }}" class="form-control" placeholder="Número, título o palabras clave">
            </div>
            <div class="col-6 col-lg-2">
                <label for="estado" class="form-label small">Estado</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($estados as $estado)
                        <option value="{{ $estado->codigo }}" @selected(($filtros['estado'] ?? '') === $estado->codigo)>{{ $estado->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="gestion" class="form-label small">Gestión</label>
                <select id="gestion" name="gestion" class="form-select">
                    <option value="">Todas</option>
                    @foreach ($gestiones as $gestion)
                        <option value="{{ $gestion }}" @selected(($filtros['gestion'] ?? '') == $gestion)>{{ $gestion }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="tipo" class="form-label small">Tipo</label>
                <select id="tipo" name="tipo" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($tipos as $tipo)
                        <option value="{{ $tipo->id_tipo_documental }}" @selected(($filtros['tipo'] ?? '') == $tipo->id_tipo_documental)>{{ $tipo->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2 d-flex gap-2">
                <button class="btn btn-outline-primary flex-fill" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
                <a href="{{ route('panel.documentos.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros" aria-label="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Número</th>
                        <th>Título</th>
                        <th>Fecha de emisión</th>
                        <th>Estado</th>
                        <th>Registrado por</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documentos as $documento)
                        <tr>
                            <td class="text-nowrap">
                                <div class="fw-semibold font-monospace">{{ $documento->numero }}</div>
                                <small class="text-body-secondary">{{ $documento->tipoDocumental->nombre }}</small>
                            </td>
                            <td style="min-width: 260px">{{ \Illuminate\Support\Str::limit($documento->titulo, 110) }}</td>
                            <td class="text-nowrap">{{ $documento->fecha_emision->format('d/m/Y') }}</td>
                            <td>@include('panel.documentos._estado', ['estado' => $documento->estadoActual])</td>
                            <td><small>{{ $documento->usuarioRegistro->nombre_completo }}</small></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('panel.documentos.show', $documento) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> Ver
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">No se encontraron documentos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($documentos->hasPages())
            <div class="card-footer bg-white">{{ $documentos->links() }}</div>
        @endif
    </div>
@endsection
