@extends('layouts.panel')

@section('titulo', 'Cambiar contraseña')

@section('contenido')
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <h1 class="h3 mb-3">Cambiar contraseña</h1>

            @if ($obligatorio)
                <div class="alert alert-warning">
                    <i class="bi bi-shield-exclamation me-1"></i>
                    Está usando una contraseña temporal asignada por el administrador. Debe reemplazarla para continuar.
                </div>
            @endif

            <form method="POST" action="{{ route('panel.perfil.contrasena.actualizar') }}" class="card border-0 shadow-sm" novalidate>
                @csrf
                @method('PUT')
                <div class="card-body">
                    @foreach ([
                        'contrasena_actual' => ['Contraseña actual', 'current-password'],
                        'contrasena' => ['Nueva contraseña', 'new-password'],
                        'contrasena_confirmation' => ['Confirmar nueva contraseña', 'new-password'],
                    ] as $campo => [$etiqueta, $autocompletar])
                        <div class="mb-3">
                            <label for="{{ $campo }}" class="form-label">{{ $etiqueta }}</label>
                            <div class="input-group has-validation">
                                <input type="password" id="{{ $campo }}" name="{{ $campo }}" autocomplete="{{ $autocompletar }}" required
                                       @class(['form-control', 'is-invalid' => $errors->has($campo)])
                                       @if ($campo === 'contrasena') aria-describedby="ayudaContrasena" @endif>
                                <button type="button" class="btn btn-outline-secondary" data-alternar-contrasena="#{{ $campo }}" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                                @error($campo) <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            @if ($campo === 'contrasena')
                                <div id="ayudaContrasena" class="form-text">Mínimo 8 caracteres, con letras y números.</div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="card-footer bg-body d-flex justify-content-end gap-2">
                    @unless ($obligatorio)
                        <a href="{{ route('panel.perfil') }}" class="btn btn-outline-secondary">Cancelar</a>
                    @endunless
                    <button type="submit" class="btn btn-gaceta"><i class="bi bi-check-lg me-1"></i> Actualizar contraseña</button>
                </div>
            </form>
        </div>
    </div>
@endsection
