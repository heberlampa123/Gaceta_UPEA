@extends('layouts.panel')

@section('titulo', 'Roles y permisos')

@section('contenido')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-0">Roles y permisos</h1>
            <p class="text-body-secondary mb-0 small">Los roles agrupan permisos. El sistema autoriza cada acción por el código del permiso.</p>
        </div>
        <a href="{{ route('panel.roles.create') }}" class="btn btn-gaceta ms-auto">
            <i class="bi bi-plus-lg me-1"></i> Nuevo rol
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Rol</th>
                        <th>Código</th>
                        <th class="text-center">Permisos</th>
                        <th class="text-center">Usuarios</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $rol)
                        <tr @class(['text-body-secondary' => ! $rol->activo])>
                            <td>
                                <div class="fw-semibold">{{ $rol->nombre }}</div>
                                <small class="text-body-secondary">{{ $rol->descripcion }}</small>
                            </td>
                            <td class="font-monospace">{{ $rol->codigo }}</td>
                            <td class="text-center">{{ $rol->permisos_count }}</td>
                            <td class="text-center">{{ $rol->usuarios_count }}</td>
                            <td>
                                <span @class(['badge', 'text-bg-success' => $rol->activo, 'text-bg-secondary' => ! $rol->activo])>
                                    {{ $rol->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('panel.roles.edit', $rol) }}" class="btn btn-sm btn-outline-primary">
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
