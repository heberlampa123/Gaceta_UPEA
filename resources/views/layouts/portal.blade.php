@extends('layouts.base')

{{-- Estructura del portal público (Figura 3): encabezado institucional, contenido y pie de página --}}
@section('cuerpo')
    <a href="#contenido" class="visually-hidden-focusable position-fixed top-0 start-0 m-2 btn btn-gaceta" style="z-index: 2000">Saltar al contenido</a>

    <header class="portal-encabezado">
        <div class="container py-2 d-flex flex-wrap align-items-center gap-3">
            <a href="{{ route('portal.inicio') }}" class="marca d-flex align-items-center gap-2 text-white text-decoration-none me-auto py-1">
                <img src="{{ asset('img/logo-upea.png') }}" alt="Escudo de la Universidad Pública de El Alto">
                <span class="lh-sm">
                    <strong class="d-block fs-5">Gaceta Universitaria</strong>
                    <small class="text-white text-opacity-75">Universidad Pública de El Alto</small>
                </span>
            </a>

            <nav class="nav-portal d-flex flex-wrap align-items-center gap-3 gap-md-4" aria-label="Navegación principal">
                <a href="{{ route('portal.inicio') }}" @class(['nav-link', 'active' => request()->routeIs('portal.inicio')])
                   @if (request()->routeIs('portal.inicio')) aria-current="page" @endif>Inicio</a>
                <a href="{{ route('portal.inicio') }}#resultados" @class(['nav-link', 'active' => request()->routeIs('portal.documentos.*')])>Documentos</a>
                <a href="{{ route('portal.acerca') }}" @class(['nav-link', 'active' => request()->routeIs('portal.acerca')])
                   @if (request()->routeIs('portal.acerca')) aria-current="page" @endif>Acerca<span class="d-none d-md-inline"> de la Gaceta</span></a>
                <a href="{{ route('login') }}" class="btn btn-sm btn-outline-light" aria-label="Acceso institucional"><i class="bi bi-lock"></i><span class="d-none d-sm-inline ms-1">Acceso institucional</span></a>
            </nav>
        </div>
        <div class="franja-wiphala"></div>
    </header>

    <main id="contenido">
        @yield('contenido')
    </main>

    <footer class="portal-pie mt-5">
        <div class="container py-4 small text-body-secondary d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <span class="d-flex align-items-center gap-2">
                <img src="{{ asset('img/logo-upea.png') }}" alt="" width="28" height="31">
                <span><strong class="text-body">Gaceta Universitaria UPEA</strong> · Portal público</span>
            </span>
            <span>Documentos oficiales publicados para consulta pública.</span>
        </div>
    </footer>
@endsection
