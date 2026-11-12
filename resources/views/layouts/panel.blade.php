@extends('layouts.base')

@php
    /*
     * Menú del panel administrativo (Figura 2 del análisis).
     * Cada opción se muestra solo si el usuario tiene el permiso indicado.
     * Las opciones con 'fase' aún no están implementadas.
     */
    $menu = [
        ['texto' => 'Inicio', 'icono' => 'house', 'ruta' => 'panel.inicio', 'activa' => 'panel.inicio'],
        ['seccion' => 'Documentos'],
        ['texto' => 'Gestión documental', 'icono' => 'folder2-open', 'permiso' => 'documento.ver', 'ruta' => 'panel.documentos.index', 'activa' => 'panel.documentos.*'],
        ['texto' => 'Revisión y publicación', 'icono' => 'clipboard-check', 'permiso' => 'documento.revisar', 'fase' => 5],
        ['texto' => 'Tipos documentales', 'icono' => 'tags', 'permiso' => 'tipo_documental.gestionar', 'ruta' => 'panel.tipos.index', 'activa' => 'panel.tipos.*'],
        ['seccion' => 'Administración'],
        ['texto' => 'Usuarios', 'icono' => 'people', 'permiso' => 'usuario.gestionar', 'ruta' => 'panel.usuarios.index', 'activa' => 'panel.usuarios.*'],
        ['texto' => 'Roles y permisos', 'icono' => 'shield-lock', 'permiso' => 'rol.gestionar', 'ruta' => 'panel.roles.index', 'activa' => 'panel.roles.*'],
        ['texto' => 'Auditoría', 'icono' => 'journal-text', 'permiso' => 'auditoria.ver', 'fase' => 7],
        ['seccion' => 'Mi cuenta'],
        ['texto' => 'Notificaciones', 'icono' => 'bell', 'fase' => 7],
        ['texto' => 'Mi perfil', 'icono' => 'person-circle', 'ruta' => 'panel.perfil', 'activa' => 'panel.perfil*'],
    ];

    $usuarioActual = auth()->user();
    $visibles = array_filter($menu, fn ($item) => ! isset($item['permiso']) || $usuarioActual->can($item['permiso']));
@endphp

@section('cuerpo')
    {{-- Menú lateral: fijo en pantallas grandes, deslizable en móviles --}}
    <aside class="panel-menu offcanvas-lg offcanvas-start text-white" tabindex="-1" id="menuPanel" aria-labelledby="menuPanelTitulo">
        <div class="offcanvas-header marca px-3 py-3">
            <a href="{{ route('panel.inicio') }}" class="d-flex align-items-center gap-2 text-white text-decoration-none">
                <i class="bi bi-journal-bookmark-fill fs-3" style="color: var(--gaceta-acento)"></i>
                <span class="lh-sm">
                    <strong class="d-block" id="menuPanelTitulo">Gaceta Universitaria</strong>
                    <small class="text-white-50">UPEA · Panel administrativo</small>
                </span>
            </a>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#menuPanel" aria-label="Cerrar menú"></button>
        </div>

        <nav class="offcanvas-body d-block p-3">
            <ul class="nav flex-column gap-1">
                @foreach ($visibles as $item)
                    @if (isset($item['seccion']))
                        <li class="seccion mt-3 mb-1 px-2">{{ $item['seccion'] }}</li>
                    @elseif (isset($item['ruta']))
                        <li class="nav-item">
                            <a href="{{ route($item['ruta']) }}"
                               @class(['nav-link', 'active' => request()->routeIs($item['activa'])])
                               @if (request()->routeIs($item['activa'])) aria-current="page" @endif>
                                <i class="bi bi-{{ $item['icono'] }}"></i> {{ $item['texto'] }}
                            </a>
                        </li>
                    @else
                        <li class="nav-item">
                            <span class="nav-link disabled" title="Disponible en la Fase {{ $item['fase'] }}">
                                <i class="bi bi-{{ $item['icono'] }}"></i> {{ $item['texto'] }}
                                <span class="badge text-bg-secondary ms-auto fw-normal">Próximamente</span>
                            </span>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>
    </aside>

    <div class="panel-contenido d-flex flex-column min-vh-100">
        {{-- Barra superior --}}
        <header class="navbar bg-white border-bottom px-3 py-2 sticky-top">
            <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuPanel" aria-controls="menuPanel" aria-label="Abrir menú">
                <i class="bi bi-list"></i>
            </button>

            <span class="navbar-text fw-semibold text-gaceta d-none d-sm-inline">@yield('titulo', 'Panel')</span>

            <div class="dropdown ms-auto">
                <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle fs-5"></i>
                    <span class="text-start lh-sm d-none d-md-block">
                        <span class="d-block">{{ $usuarioActual->nombre_completo }}</span>
                        <small class="text-body-secondary">{{ $usuarioActual->roles->pluck('nombre')->join(', ') }}</small>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('panel.perfil') }}"><i class="bi bi-person me-2"></i>Mi perfil</a></li>
                    <li><a class="dropdown-item" href="{{ route('panel.perfil.contrasena') }}"><i class="bi bi-key me-2"></i>Cambiar contraseña</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="flex-grow-1 p-3 p-lg-4">
            @include('partials.alertas')
            @yield('contenido')
        </main>

        <footer class="text-center text-body-secondary small py-3 border-top bg-white">
            Gaceta Universitaria · Universidad Pública de El Alto
        </footer>
    </div>
@endsection
