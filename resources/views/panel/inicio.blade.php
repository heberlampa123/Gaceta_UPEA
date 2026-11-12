@extends('layouts.panel')

@section('titulo', 'Inicio')

@section('contenido')
    <div class="mb-4">
        <h1 class="h3 mb-1">Bienvenido(a), {{ $usuario->nombres }}</h1>
        <p class="text-body-secondary mb-0">
            @if ($usuario->ultimo_acceso)
                Último acceso: {{ $usuario->ultimo_acceso->format('d/m/Y H:i') }}
            @endif
        </p>
    </div>

    @if ($estados->isNotEmpty())
        <h2 class="h5 mb-3">Documentos por estado</h2>
        <div class="row g-3 mb-4">
            @foreach ($estados as $estado)
                <div class="col-sm-6 col-xl">
                    <div class="card tarjeta-estado shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-body-secondary small">{{ $estado->nombre }}</div>
                            <div class="fs-2 fw-semibold">{{ $estado->documentos_count }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h2 class="h6 mb-0"><i class="bi bi-clock-history me-1"></i> Mi actividad reciente</h2>
        </div>
        <ul class="list-group list-group-flush">
            @forelse ($actividad as $registro)
                <li class="list-group-item d-flex justify-content-between">
                    <span>{{ $registro->etiqueta() }} @if ($registro->entidad) <span class="text-body-secondary">· {{ $registro->entidad }}</span> @endif</span>
                    <small class="text-body-secondary">{{ $registro->fecha->format('d/m/Y H:i') }}</small>
                </li>
            @empty
                <li class="list-group-item text-body-secondary">Sin actividad registrada.</li>
            @endforelse
        </ul>
    </div>
@endsection
