@extends('layouts.base')

@section('titulo', 'Iniciar sesión')

@section('cuerpo')
    <div class="auth-pagina">
        {{-- Lado institucional (en celulares se reduce a una franja superior) --}}
        <aside class="auth-lado d-flex flex-column">
            <div class="franja-wiphala"></div>
            <div class="flex-grow-1 d-flex flex-column align-items-center justify-content-center text-center p-4 p-lg-5">
                <img src="{{ asset('img/logo-upea.png') }}" alt="Escudo de la Universidad Pública de El Alto" class="mb-4 d-none d-lg-block">
                <img src="{{ asset('img/logo-upea.png') }}" alt="" class="mb-3 d-lg-none" style="width: 5rem">
                <h1 class="h2 fw-bold mb-1">Gaceta Universitaria</h1>
                <p class="text-white text-opacity-75 mb-0">Universidad Pública de El Alto</p>
                <p class="text-white text-opacity-50 small mt-4 mb-0 d-none d-lg-block" style="max-width: 24rem">
                    Sistema de registro, revisión y publicación de documentos oficiales.
                </p>
            </div>
        </aside>

        <main class="d-flex align-items-center justify-content-center p-4 p-sm-5 bg-body">
            <div class="auth-formulario">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <a href="{{ route('portal.inicio') }}" class="small"><i class="bi bi-arrow-left"></i> Volver al portal público</a>
                </div>

                <h2 class="h3 fw-bold mb-1">Acceso institucional</h2>
                <p class="text-body-secondary mb-4">Ingrese con la cuenta asignada por el administrador.</p>

                @if (session('estado'))
                    <div class="alert alert-info py-2">{{ session('estado') }}</div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="usuario" class="form-label fw-medium">Usuario o correo</label>
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
                        <label for="contrasena" class="form-label fw-medium">Contraseña</label>
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

                    <button type="submit" class="btn btn-gaceta btn-lg w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Ingresar
                    </button>
                </form>

                <p class="small text-body-secondary mt-4 mb-0">
                    <i class="bi bi-info-circle me-1"></i> ¿Olvidó su contraseña? Solicite el restablecimiento al administrador del sistema.
                </p>
            </div>
        </main>
    </div>
@endsection
