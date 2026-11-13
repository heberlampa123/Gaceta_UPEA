@extends('layouts.base')

@use('App\Http\Controllers\Api\NotificacionController')
@use('App\Http\Resources\NotificacionResource')
@use('App\Models\Documento')
@use('App\Models\EstadoDocumento')

@php
    /*
     * Menú del panel administrativo (Figura 2 del análisis).
     * Cada opción se muestra solo si el usuario tiene el permiso indicado; un título de sección
     * se muestra solo si debajo queda al menos una opción visible.
     * 'contador': 'notificaciones' (no leídas) o 'revision' (documentos que esperan al revisor).
     */
    $menu = [
        ['texto' => 'Inicio', 'icono' => 'grid', 'ruta' => 'panel.inicio', 'activa' => 'panel.inicio'],
        ['texto' => 'Notificaciones', 'icono' => 'bell', 'ruta' => 'panel.notificaciones.index', 'activa' => 'panel.notificaciones.*', 'contador' => 'notificaciones'],
        ['seccion' => 'Gestión documental'],
        ['texto' => 'Registrar documento', 'icono' => 'file-earmark-plus', 'permiso' => 'documento.registrar', 'ruta' => 'panel.documentos.create', 'activa' => 'panel.documentos.create'],
        ['texto' => 'Documentos registrados', 'icono' => 'files', 'permiso' => 'documento.ver', 'ruta' => 'panel.documentos.index', 'activa' => ['panel.documentos.index', 'panel.documentos.show', 'panel.documentos.edit']],
        ['texto' => 'Revisión y publicación', 'icono' => 'clipboard-check', 'permiso' => 'documento.revisar', 'ruta' => 'panel.revision.index', 'activa' => 'panel.revision.*', 'contador' => 'revision'],
        ['seccion' => 'Administración'],
        ['texto' => 'Tipos documentales', 'icono' => 'diagram-2', 'permiso' => 'tipo_documental.gestionar', 'ruta' => 'panel.tipos.index', 'activa' => 'panel.tipos.*'],
        ['texto' => 'Usuarios', 'icono' => 'people', 'permiso' => 'usuario.gestionar', 'ruta' => 'panel.usuarios.index', 'activa' => 'panel.usuarios.*'],
        ['texto' => 'Roles y permisos', 'icono' => 'shield-lock', 'permiso' => 'rol.gestionar', 'ruta' => 'panel.roles.index', 'activa' => 'panel.roles.*'],
        ['texto' => 'Auditoría', 'icono' => 'clock-history', 'permiso' => 'auditoria.ver', 'ruta' => 'panel.auditoria.index', 'activa' => 'panel.auditoria.*'],
    ];

    $usuarioActual = auth()->user();
    $visibles = array_values(array_filter($menu, fn ($item) => ! isset($item['permiso']) || $usuarioActual->can($item['permiso'])));
    // Quita los títulos de sección que quedaron sin opciones debajo.
    $visibles = array_values(array_filter($visibles, fn ($item, $i) => ! isset($item['seccion'])
        || (isset($visibles[$i + 1]) && ! isset($visibles[$i + 1]['seccion'])), ARRAY_FILTER_USE_BOTH));

    // Campana de notificaciones (Fase 7): valores iniciales; resources/js/notificaciones.js los actualiza con Axios.
    $noLeidas = $usuarioActual->notificaciones()->noLeidas()->count();
    $notificacionesRecientes = NotificacionResource::collection(
        $usuarioActual->notificaciones()->latest('fecha_creacion')->latest('id_notificacion')->limit(NotificacionController::RECIENTES)->get()
    )->resolve(request());

    // Documentos que esperan al revisor (registrados por otros): pendientes y, si puede publicar, aprobados.
    $porRevisar = $usuarioActual->can('documento.revisar')
        ? Documento::activos()->where('id_usuario_registro', '<>', $usuarioActual->id_usuario)
            ->whereHas('estadoActual', fn ($q) => $q->whereIn('codigo', array_filter([
                EstadoDocumento::PENDIENTE_REVISION,
                $usuarioActual->can('documento.publicar') ? EstadoDocumento::APROBADO : null,
            ])))->count()
        : 0;
    $contadores = ['notificaciones' => $noLeidas, 'revision' => $porRevisar];

    $iniciales = mb_strtoupper(mb_substr($usuarioActual->nombres, 0, 1).mb_substr($usuarioActual->apellidos ?? '', 0, 1));
@endphp

@section('cuerpo')
    <a href="#contenido" class="visually-hidden-focusable position-fixed top-0 start-0 m-2 btn btn-gaceta" style="z-index: 2000">Saltar al contenido</a>

    {{-- Menú lateral: fijo en pantallas grandes, deslizable en celulares --}}
    <aside class="panel-menu offcanvas-lg offcanvas-start" tabindex="-1" id="menuPanel" aria-labelledby="menuPanelTitulo">
        <div class="franja-wiphala"></div>
        <div class="offcanvas-header marca px-3 py-3">
            <a href="{{ route('panel.inicio') }}" class="d-flex align-items-center gap-2 text-white text-decoration-none">
                <img src="{{ asset('img/logo-upea.png') }}" alt="Escudo de la UPEA">
                <span class="lh-sm">
                    <strong class="d-block" id="menuPanelTitulo">Gaceta Universitaria</strong>
                    <small class="text-white text-opacity-50">UPEA · Panel administrativo</small>
                </span>
            </a>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#menuPanel" aria-label="Cerrar menú"></button>
        </div>

        <nav class="offcanvas-body d-block p-3 flex-grow-1" aria-label="Menú principal">
            <ul class="nav flex-column gap-1">
                @foreach ($visibles as $item)
                    @if (isset($item['seccion']))
                        <li class="seccion mt-3 mb-1 px-2">{{ $item['seccion'] }}</li>
                    @else
                        @php($activa = request()->routeIs(...(array) $item['activa']))
                        <li class="nav-item">
                            <a href="{{ route($item['ruta']) }}" @class(['nav-link', 'active' => $activa]) @if ($activa) aria-current="page" @endif>
                                <i class="bi bi-{{ $item['icono'] }}"></i> {{ $item['texto'] }}
                                @if ($clave = $item['contador'] ?? null)
                                    <span class="badge rounded-pill contador" @if ($clave === 'notificaciones') data-contador-menu @endif
                                          @if (! $contadores[$clave]) hidden @endif>{{ $contadores[$clave] }}</span>
                                @endif
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>

        <div class="pie-menu p-3">
            <a href="{{ route('panel.perfil') }}" @class(['nav-link', 'active' => request()->routeIs('panel.perfil*')])>
                <i class="bi bi-person-circle"></i> Mi perfil
            </a>
        </div>
    </aside>

    <div class="panel-contenido d-flex flex-column min-vh-100">
        {{-- Barra superior --}}
        <header class="panel-barra d-flex align-items-center gap-2 gap-md-3 px-3 px-lg-4 sticky-top">
            <button class="btn btn-icono d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuPanel" aria-controls="menuPanel" aria-label="Abrir menú">
                <i class="bi bi-list fs-4"></i>
            </button>

            <nav class="d-none d-sm-flex gap-4" aria-label="Secciones del sistema">
                <a href="{{ route('panel.inicio') }}" class="enlace-barra active" aria-current="page">Panel administrativo</a>
                <a href="{{ route('portal.inicio') }}" class="enlace-barra" target="_blank" rel="noopener">Portal público <i class="bi bi-box-arrow-up-right small"></i></a>
            </nav>

            <div class="ms-auto d-flex align-items-center gap-1">
                @can('documento.ver')
                    <a href="{{ route('panel.documentos.index') }}" class="btn btn-icono" aria-label="Buscar documentos" title="Buscar documentos">
                        <i class="bi bi-search fs-5"></i>
                    </a>
                @endcan


                {{-- Campana de notificaciones (se actualiza con Axios cada minuto) --}}
                <div class="dropdown" data-notificaciones
                     data-api="{{ route('api.notificaciones.resumen') }}" data-leer-todas="{{ route('api.notificaciones.leer-todas') }}">
                    <button class="btn btn-icono position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                            aria-expanded="false" aria-label="Notificaciones" data-campana>
                        <i class="bi bi-bell fs-5"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger" data-contador
                              @if (! $noLeidas) hidden @endif>{{ $noLeidas }}</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0 shadow" style="width: min(22rem, 92vw)">
                        <div class="d-flex align-items-center px-3 py-2 border-bottom">
                            <strong>Notificaciones</strong>
                            <button type="button" class="btn btn-link btn-sm ms-auto p-0 text-decoration-none" data-leer-todas-boton>Marcar todas como leídas</button>
                        </div>
                        <div class="list-group list-group-flush" data-lista style="max-height: 360px; overflow-y: auto">
                            @foreach ($notificacionesRecientes as $n)
                                @include('panel.notificaciones._item', ['n' => $n])
                            @endforeach
                        </div>
                        <p class="text-center text-body-secondary small py-3 mb-0" data-vacio @if (count($notificacionesRecientes)) hidden @endif>No tiene notificaciones.</p>
                        <a href="{{ route('panel.notificaciones.index') }}" class="d-block text-center py-2 border-top small">Ver todas</a>
                    </div>
                    <template data-plantilla>
                        @include('panel.notificaciones._item', ['n' => []])
                    </template>
                </div>

                <div class="vr mx-2 d-none d-md-block"></div>

                <div class="dropdown">
                    <button class="btn d-flex align-items-center gap-2 border-0 px-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menú de la cuenta">
                        <span class="text-end lh-sm d-none d-md-block">
                            <span class="d-block fw-semibold">{{ $usuarioActual->nombre_completo }}</span>
                            <small class="text-body-secondary">{{ $usuarioActual->roles->pluck('nombre')->join(', ') }}</small>
                        </span>
                        <span class="avatar" aria-hidden="true">{{ $iniciales }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
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
            </div>
        </header>

        {{-- Migas de pan: cada vista agrega las suyas con @section('migas') --}}
        <nav class="panel-migas px-3 px-lg-4 py-2" aria-label="Ubicación">
            <ol class="breadcrumb">
                @if (request()->routeIs('panel.inicio'))
                    <li class="breadcrumb-item">Inicio</li>
                    <li class="breadcrumb-item active" aria-current="page">Panel principal</li>
                @else
                    <li class="breadcrumb-item"><a href="{{ route('panel.inicio') }}">Inicio</a></li>
                    @hasSection('migas')
                        @yield('migas')
                    @else
                        <li class="breadcrumb-item active" aria-current="page">@yield('titulo')</li>
                    @endif
                @endif
            </ol>
        </nav>

        <main id="contenido" class="flex-grow-1 p-3 p-lg-4">
            @include('partials.alertas')
            @yield('contenido')
        </main>

        <footer class="d-flex flex-wrap justify-content-between gap-2 text-body-secondary small px-3 px-lg-4 py-3 border-top bg-body">
            <span>Gaceta Universitaria UPEA · Universidad Pública de El Alto</span>
            <span>Versión inicial: resoluciones del HCU</span>
        </footer>
    </div>
@endsection
