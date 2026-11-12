@extends('layouts.base')

@section('titulo', 'Portal')

@section('cuerpo')
    {{-- Página provisional: el portal público con búsqueda y filtros se desarrolla en la Fase 6. --}}
    <header class="text-white py-4" style="background-color: var(--gaceta-primario)">
        <div class="container d-flex align-items-center gap-3">
            <i class="bi bi-journal-bookmark-fill fs-1" style="color: var(--gaceta-acento)"></i>
            <div>
                <h1 class="h3 mb-0">Gaceta Universitaria</h1>
                <p class="mb-0 text-white-50">Universidad Pública de El Alto</p>
            </div>
            <a href="{{ route('login') }}" class="btn btn-outline-light ms-auto">
                <i class="bi bi-box-arrow-in-right me-1"></i> Panel administrativo
            </a>
        </div>
    </header>

    <main class="container py-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-5 text-center">
                <i class="bi bi-search text-gaceta" style="font-size: 3rem"></i>
                <h2 class="h4 mt-3">Consulta de documentos oficiales</h2>
                <p class="text-body-secondary mb-0">
                    Aquí podrá buscar, consultar y descargar las resoluciones publicadas del Honorable Consejo Universitario.
                    <br>Esta sección se encuentra en desarrollo.
                </p>
            </div>
        </div>
    </main>

    <footer class="text-center text-body-secondary small py-4">
        Gaceta Universitaria · Universidad Pública de El Alto
    </footer>
@endsection
