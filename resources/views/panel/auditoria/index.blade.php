@extends('layouts.panel')

@section('titulo', 'Auditoría')

@section('contenido')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-0">Auditoría</h1>
            <p class="text-body-secondary small mb-0">Operaciones realizadas en el sistema. Los registros no se pueden modificar ni eliminar.</p>
        </div>
        <a href="{{ route('panel.auditoria.exportar', request()->query()) }}" class="btn btn-outline-success ms-auto">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Exportar a Excel (CSV)
        </a>
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-3">
                <label for="usuario" class="form-label small">Usuario</label>
                <select id="usuario" name="usuario" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach ($usuarios as $u)
                        <option value="{{ $u->id_usuario }}" @selected(($filtros['usuario'] ?? null) == $u->id_usuario)>{{ $u->apellidos }} {{ $u->nombres }} ({{ $u->nombre_usuario }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="accion" class="form-label small">Acción</label>
                <select id="accion" name="accion" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach ($acciones as $codigo => $etiqueta)
                        <option value="{{ $codigo }}" @selected(($filtros['accion'] ?? null) === $codigo)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="entidad" class="form-label small">Entidad</label>
                <select id="entidad" name="entidad" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach ($entidades as $entidad)
                        <option value="{{ $entidad }}" @selected(($filtros['entidad'] ?? null) === $entidad)>{{ $entidad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-1">
                <label for="registro" class="form-label small">ID</label>
                <input type="number" id="registro" name="registro" min="1" value="{{ $filtros['registro'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-1">
                <label for="desde" class="form-label small">Desde</label>
                <input type="date" id="desde" name="desde" value="{{ $filtros['desde'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-1">
                <label for="hasta" class="form-label small">Hasta</label>
                <input type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button class="btn btn-sm btn-outline-primary flex-fill" type="submit" aria-label="Filtrar"><i class="bi bi-funnel"></i></button>
                <a href="{{ route('panel.auditoria.index') }}" class="btn btn-sm btn-outline-secondary" title="Limpiar filtros" aria-label="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white small text-body-secondary">{{ number_format($registros->total(), 0, ',', '.') }} registro(s)</div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Entidad</th>
                        <th>IP</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($registros as $registro)
                        <tr>
                            <td class="text-nowrap">{{ $registro->fecha->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $registro->usuario?->nombre_completo ?? '—' }} @if ($registro->usuario) <span class="text-body-secondary">({{ $registro->usuario->nombre_usuario }})</span> @endif</td>
                            <td>
                                <span @class(['badge', 'text-bg-danger' => str_contains($registro->accion, 'FALLIDO') || str_contains($registro->accion, 'BLOQUEO'), 'text-bg-light border' => ! (str_contains($registro->accion, 'FALLIDO') || str_contains($registro->accion, 'BLOQUEO'))])>
                                    {{ $registro->etiqueta() }}
                                </span>
                            </td>
                            <td class="text-nowrap">{{ $registro->entidad }} @if ($registro->id_registro) <span class="text-body-secondary">#{{ $registro->id_registro }}</span> @endif</td>
                            <td class="font-monospace">{{ $registro->ip }}</td>
                            <td style="min-width: 220px">
                                @if ($registro->detalle)
                                    <details>
                                        <summary class="text-primary">Ver detalle</summary>
                                        <pre class="bg-light border rounded p-2 mt-1 mb-0 small" style="max-height: 240px; white-space: pre-wrap">{{ json_encode($registro->detalle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
                                @else
                                    <span class="text-body-secondary">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">No hay registros con los filtros indicados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($registros->hasPages())
            <div class="card-footer bg-white">{{ $registros->links() }}</div>
        @endif
    </div>
@endsection
