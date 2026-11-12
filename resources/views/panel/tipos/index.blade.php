@extends('layouts.panel')

@section('titulo', 'Tipos documentales')

@section('contenido')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-0">Tipos documentales</h1>
            <p class="text-body-secondary mb-0 small">Cada tipo puede tener sus propios campos. Agregar un tipo no requiere modificar el sistema.</p>
        </div>
        <a href="{{ route('panel.tipos.create') }}" class="btn btn-gaceta ms-auto">
            <i class="bi bi-plus-lg me-1"></i> Nuevo tipo
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tipo documental</th>
                        <th>Código</th>
                        <th>Campos adicionales</th>
                        <th class="text-center">Documentos</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tipos as $tipo)
                        <tr @class(['text-body-secondary' => ! $tipo->activo])>
                            <td>
                                <div class="fw-semibold">{{ $tipo->nombre }}</div>
                                <small class="text-body-secondary">{{ $tipo->descripcion }}</small>
                            </td>
                            <td class="font-monospace">{{ $tipo->codigo }}</td>
                            <td>
                                @forelse ($tipo->campos() as $campo)
                                    <span class="badge text-bg-light border" title="{{ \App\Models\TipoDocumental::TIPOS_CAMPO[$campo['tipo']] ?? $campo['tipo'] }}">
                                        {{ $campo['etiqueta'] }}@if ($campo['obligatorio']) *@endif
                                    </span>
                                @empty
                                    <small class="text-body-secondary">Ninguno</small>
                                @endforelse
                            </td>
                            <td class="text-center">{{ $tipo->documentos_count }}</td>
                            <td>
                                <span @class(['badge', 'text-bg-success' => $tipo->activo, 'text-bg-secondary' => ! $tipo->activo])>
                                    {{ $tipo->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('panel.tipos.edit', $tipo) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Editar
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
