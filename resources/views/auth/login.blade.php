@extends('layouts.base')

@section('titulo', 'Iniciar sesión')
@section('clase_body', 'auth-fondo d-flex align-items-center justify-content-center p-3')

@section('cuerpo')
    <main class="card auth-tarjeta shadow-lg">
        <div class="card-body p-4 p-sm-5">
            <div class="text-center mb-4">
                <i class="bi bi-journal-bookmark-fill text-gaceta" style="font-size: 2.75rem"></i>
                <h1 class="h4 mt-2 mb-1">Gaceta Universitaria</h1>
                <p class="text-body-secondary mb-0">Universidad Pública de El Alto</p>
            </div>

            @if (session('estado'))
                <div class="alert alert-info py-2">{{ session('estado') }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="usuario" class="form-label">Usuario o correo</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" id="usuario" name="usuario" value="{{ old('usuario') }}"
                               @class(['form-control', 'is-invalid' => $errors->has('usuario')])
                               autocomplete="username" autofocus required>
                        @error('usuario')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-4">
                    <label for="contrasena" class="form-label">Contraseña</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" id="contrasena" name="contrasena"
                               @class(['form-control', 'is-invalid' => $errors->has('contrasena')])
                               autocomplete="current-password" required>
                        <button type="button" class="btn btn-outline-secondary" data-alternar-contrasena="#contrasena" aria-label="Mostrar contraseña">
                            <i class="bi bi-eye"></i>
                        </button>
                        @error('contrasena')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <button type="submit" class="btn btn-gaceta w-100 py-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Ingresar
                </button>
            </form>

            <p class="text-center small text-body-secondary mt-4 mb-0">
                ¿Olvidó su contraseña? Solicite el restablecimiento al administrador del sistema.
            </p>
        </div>
        <div class="card-footer bg-transparent text-center py-3">
            <a href="{{ route('portal.inicio') }}" class="small text-decoration-none">
                <i class="bi bi-arrow-left"></i> Volver al portal público
            </a>
        </div>
    </main>
@endsection
