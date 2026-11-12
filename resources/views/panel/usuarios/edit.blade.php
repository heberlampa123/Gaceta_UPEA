@extends('layouts.panel')

@section('titulo', 'Editar usuario')

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('panel.usuarios.index') }}">Usuarios</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $usuario->nombre_usuario }}</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">{{ $usuario->nombre_completo }}</h1>

    <div class="row g-3">
        <div class="col-xl-8">
            <form method="POST" action="{{ route('panel.usuarios.update', $usuario) }}" class="card border-0 shadow-sm">
                @csrf
                @method('PUT')
                <div class="card-header bg-white"><h2 class="h6 mb-0">Datos de la cuenta</h2></div>
                <div class="card-body">
                    @include('panel.usuarios._form')
                </div>
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('panel.usuarios.index') }}" class="btn btn-outline-secondary">Volver</a>
                    <button type="submit" class="btn btn-gaceta"><i class="bi bi-check-lg me-1"></i> Guardar cambios</button>
                </div>
            </form>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Estado de la cuenta</h2></div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Estado</span>
                        @if ($usuario->activo)
                            <span class="badge text-bg-success">Activa</span>
                        @else
                            <span class="badge text-bg-secondary">Inactiva</span>
                        @endif
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Bloqueo</span>
                        <span>{{ $usuario->estaBloqueado() ? 'Hasta las '.$usuario->bloqueado_hasta->format('H:i') : 'No' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Intentos fallidos</span><span>{{ $usuario->intentos_fallidos }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Último acceso</span><span>{{ $usuario->ultimo_acceso?->format('d/m/Y H:i') ?? 'Nunca' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Creada</span><span>{{ $usuario->fecha_creacion?->format('d/m/Y') }}</span>
                    </li>
                </ul>
                <div class="card-body d-grid gap-2">
                    @if ($usuario->estaBloqueado())
                        <form method="POST" action="{{ route('panel.usuarios.desbloquear', $usuario) }}">
                            @csrf @method('PATCH')
                            <button class="btn btn-outline-primary w-100"><i class="bi bi-unlock me-1"></i> Desbloquear</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('panel.usuarios.restablecer-contrasena', $usuario) }}"
                          data-confirmar="Se generará una contraseña temporal y el usuario deberá cambiarla al ingresar. ¿Continuar?">
                        @csrf @method('PATCH')
                        <button class="btn btn-outline-warning w-100"><i class="bi bi-key me-1"></i> Restablecer contraseña</button>
                    </form>

                    @unless ($usuario->is(auth()->user()))
                        @if ($usuario->activo)
                            <form method="POST" action="{{ route('panel.usuarios.desactivar', $usuario) }}"
                                  data-confirmar="El usuario no podrá ingresar al sistema. Sus registros se conservan. ¿Desactivar la cuenta?">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline-danger w-100"><i class="bi bi-person-x me-1"></i> Desactivar cuenta</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('panel.usuarios.activar', $usuario) }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline-success w-100"><i class="bi bi-person-check me-1"></i> Activar cuenta</button>
                            </form>
                        @endif
                    @endunless
                </div>
            </div>
        </div>
    </div>
@endsection
