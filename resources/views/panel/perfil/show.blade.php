@extends('layouts.panel')

@section('titulo', 'Mi perfil')

@section('contenido')
    <h1 class="h3 mb-3">Mi perfil</h1>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-body d-flex align-items-center">
                    <h2 class="h6 mb-0">Datos de la cuenta</h2>
                    <a href="{{ route('panel.perfil.contrasena') }}" class="btn btn-sm btn-outline-primary ms-auto">
                        <i class="bi bi-key me-1"></i> Cambiar contraseña
                    </a>
                </div>
                <dl class="row card-body mb-0">
                    <dt class="col-sm-4">Nombre</dt>
                    <dd class="col-sm-8">{{ $usuario->nombre_completo }}</dd>
                    <dt class="col-sm-4">Usuario</dt>
                    <dd class="col-sm-8 font-monospace">{{ $usuario->nombre_usuario }}</dd>
                    <dt class="col-sm-4">Correo</dt>
                    <dd class="col-sm-8">{{ $usuario->correo }}</dd>
                    <dt class="col-sm-4">Cargo</dt>
                    <dd class="col-sm-8">{{ $usuario->cargo ?? '—' }}</dd>
                    <dt class="col-sm-4">Roles</dt>
                    <dd class="col-sm-8">{{ $usuario->roles->pluck('nombre')->join(', ') ?: '—' }}</dd>
                    <dt class="col-sm-4">Último acceso</dt>
                    <dd class="col-sm-8">{{ $usuario->ultimo_acceso?->format('d/m/Y H:i') ?? '—' }}</dd>
                </dl>
                <div class="card-footer bg-body small text-body-secondary">
                    Para modificar sus datos o roles, comuníquese con el administrador del sistema.
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-body"><h2 class="h6 mb-0">Mis permisos</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($permisos as $codigo)
                        <li class="list-group-item font-monospace small"><i class="bi bi-check2 text-success me-1"></i>{{ $codigo }}</li>
                    @empty
                        <li class="list-group-item text-body-secondary">Sin permisos asignados.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
