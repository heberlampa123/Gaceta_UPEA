@extends('layouts.base')

{{-- Estructura del portal público (Figura 3): encabezado institucional, contenido y pie de página --}}
@section('cuerpo')
    <a href="#contenido" class="visually-hidden-focusable position-absolute top-0 start-0 m-2 btn btn-light">Saltar al contenido</a>

    <header class="text-white" style="background-color: var(--gaceta-primario)">
        <div class="container py-3 d-flex flex-wrap align-items-center gap-3">
            <a href="{{ route('portal.inicio') }}" class="d-flex align-items-center gap-2 text-white text-decoration-none">
                <i class="bi bi-journal-bookmark-fill fs-1" style="color: var(--gaceta-acento)"></i>
                <span class="lh-sm">
                    <strong class="d-block fs-4">Gaceta Universitaria</strong>
                    <small class="text-white-50">Universidad Pública de El Alto</small>
                </span>
            </a>
            <nav class="ms-auto d-flex gap-2" aria-label="Navegación principal">
                <a href="{{ route('portal.inicio') }}" class="btn btn-sm btn-outline-light"><i class="bi bi-search me-1"></i> Buscar documentos</a>
                <a href="{{ route('login') }}" class="btn btn-sm btn-light"><i class="bi bi-box-arrow-in-right me-1"></i> Panel administrativo</a>
            </nav>
        </div>
    </header>

    <main id="contenido" class="container py-4">
        @yield('contenido')
    </main>

    <footer class="border-top bg-white mt-4">
        <div class="container py-4 small text-body-secondary d-flex flex-wrap gap-3 justify-content-between">
            <span><strong>Gaceta Universitaria</strong> · Universidad Pública de El Alto</span>
            <span>Documentos oficiales publicados para consulta pública.</span>
        </div>
    </footer>
@endsection
