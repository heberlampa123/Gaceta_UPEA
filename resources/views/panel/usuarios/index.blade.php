@extends('layouts.panel')

@section('titulo', 'Usuarios')

@section('contenido')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Usuarios</h1>
        <a href="{{ route('panel.usuarios.create') }}" class="btn btn-gaceta ms-auto">
            <i class="bi bi-person-plus me-1"></i> Nuevo usuario
        </a>
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-5">
                <label for="buscar" class="form-label small">Buscar</label>
                <input type="search" id="buscar" name="buscar" value="{{ $filtros['buscar'] ?? '' }}" class="form-control" placeholder="Nombre, usuario o correo">
            </div>
            <div class="col-6 col-md-3">
                <label for="estado" class="form-label small">Estado</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="">Todos</option>
                    @foreach (['activos' => 'Activos', 'inactivos' => 'Inactivos', 'bloqueados' => 'Bloqueados'] as $valor => $texto)
                        <option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="rol" class="form-label small">Rol</label>
                <select id="rol" name="rol" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($roles as $rol)
                        <option value="{{ $rol->id_rol }}" @selected(($filtros['rol'] ?? '') == $rol->id_rol)>{{ $rol->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-outline-primary flex-fill" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
                <a href="{{ route('panel.usuarios.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros" aria-label="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Roles</th>
                        <th>Estado</th>
                        <th>Último acceso</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($usuarios as $usuario)
                        <tr @class(['text-body-secondary' => ! $usuario->activo])>
                            <td>
                                <div class="fw-semibold">{{ $usuario->nombre_completo }}</div>
                                <small class="text-body-secondary">{{ $usuario->correo }}</small>
                            </td>
                            <td class="font-monospace">{{ $usuario->nombre_usuario }}</td>
                            <td>
                                @foreach ($usuario->roles as $rol)
                                    <span class="badge text-bg-light border">{{ $rol->nombre }}</span>
                                @endforeach
                            </td>
                            <td>
                                @if (! $usuario->activo)
                                    <span class="badge text-bg-secondary">Inactivo</span>
                                @elseif ($usuario->estaBloqueado())
                                    <span class="badge text-bg-danger" title="Hasta {{ $usuario->bloqueado_hasta->format('H:i') }}">Bloqueado</span>
                                @else
                                    <span class="badge text-bg-success">Activo</span>
                                @endif
                                @if ($usuario->debe_cambiar_contrasena)
                                    <span class="badge text-bg-warning" title="Debe cambiar su contraseña al ingresar">Contraseña temporal</span>
                                @endif
                            </td>
                            <td><small>{{ $usuario->ultimo_acceso?->format('d/m/Y H:i') ?? 'Nunca' }}</small></td>
                            <td class="text-end">
                                <a href="{{ route('panel.usuarios.edit', $usuario) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Editar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">No se encontraron usuarios.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($usuarios->hasPages())
            <div class="card-footer bg-white">{{ $usuarios->links() }}</div>
        @endif
    </div>
@endsection
