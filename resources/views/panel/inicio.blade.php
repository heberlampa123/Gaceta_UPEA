@extends('layouts.panel')

@section('titulo', 'Inicio')

@section('contenido')
    <div class="mb-4">
        <h1 class="h3 mb-1">Bienvenido(a), {{ $usuario->nombres }}</h1>
        <p class="text-body-secondary mb-0">
            {{ $usuario->roles->pluck('nombre')->join(', ') }}
            @if ($usuario->ultimo_acceso) · Último acceso: {{ $usuario->ultimo_acceso->format('d/m/Y H:i') }} @endif
        </p>
    </div>

    {{-- Mis pendientes según el rol --}}
    @if ($pendientes->isNotEmpty())
        <h2 class="h5 mb-3">Mis pendientes</h2>
        <div class="row g-3 mb-4">
            @foreach ($pendientes as [$texto, $cantidad, $enlace, $icono, $color])
                <div class="col-sm-6 col-xl-3">
                    <a href="{{ $enlace }}" class="card border-0 shadow-sm h-100 text-decoration-none">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="rounded-circle d-inline-flex align-items-center justify-content-center bg-{{ $color }}-subtle text-{{ $color }}-emphasis" style="width: 3rem; height: 3rem">
                                <i class="bi bi-{{ $icono }} fs-4"></i>
                            </span>
                            <span>
                                <span class="d-block fs-3 fw-semibold text-body lh-1">{{ $cantidad }}</span>
                                <span class="small text-body-secondary">{{ $texto }}</span>
                            </span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Documentos por estado --}}
    @if ($estados->isNotEmpty())
        <h2 class="h5 mb-3">Documentos por estado</h2>
        <div class="row g-3 mb-4">
            @foreach ($estados as $estado)
                <div class="col-sm-6 col-xl">
                    <a href="{{ route('panel.documentos.index', ['estado' => $estado->codigo]) }}" class="card tarjeta-estado shadow-sm h-100 text-decoration-none">
                        <div class="card-body">
                            <div class="text-body-secondary small">{{ $estado->nombre }}</div>
                            <div class="fs-2 fw-semibold text-body">{{ $estado->documentos_count }}</div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-3">
        {{-- Notificaciones sin leer --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex align-items-center">
                    <h2 class="h6 mb-0"><i class="bi bi-bell me-1"></i> Notificaciones sin leer</h2>
                    <a href="{{ route('panel.notificaciones.index') }}" class="small ms-auto">Ver todas</a>
                </div>
                <div class="list-group list-group-flush">
                    @forelse ($notificaciones as $notificacion)
                        <a href="{{ route('panel.notificaciones.abrir', $notificacion) }}" class="list-group-item list-group-item-action d-flex gap-2">
                            <i class="bi bi-{{ $notificacion->icono() }} text-gaceta mt-1"></i>
                            <span class="small flex-grow-1">
                                <strong class="d-block">{{ $notificacion->titulo }}</strong>
                                {{ $notificacion->mensaje }}
                            </span>
                            <small class="text-body-secondary text-nowrap">{{ $notificacion->fecha_creacion->diffForHumans() }}</small>
                        </a>
                    @empty
                        <div class="list-group-item text-body-secondary small">No tiene notificaciones sin leer.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Documentos con movimiento reciente --}}
        @if ($recientes->isNotEmpty())
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-folder2-open me-1"></i> Documentos con movimiento reciente</h2></div>
                    <div class="list-group list-group-flush">
                        @foreach ($recientes as $documento)
                            <a href="{{ route('panel.documentos.show', $documento) }}" class="list-group-item list-group-item-action small d-flex gap-2 align-items-center">
                                <span class="font-monospace">{{ $documento->numero }}</span>
                                <span class="text-truncate flex-grow-1">{{ $documento->titulo }}</span>
                                @include('panel.documentos._estado', ['estado' => $documento->estadoActual])
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Mi actividad reciente --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-clock-history me-1"></i> Mi actividad reciente</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($actividad as $registro)
                        <li class="list-group-item d-flex justify-content-between small">
                            <span>{{ $registro->etiqueta() }} @if ($registro->entidad) <span class="text-body-secondary">· {{ $registro->entidad }}</span> @endif</span>
                            <span class="text-body-secondary">{{ $registro->fecha->format('d/m/Y H:i') }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary small">Sin actividad registrada.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
