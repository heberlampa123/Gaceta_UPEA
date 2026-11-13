@extends('layouts.panel')

@section('titulo', 'Notificaciones')

@section('contenido')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Notificaciones</h1>
        <span class="text-body-secondary">{{ $noLeidas }} sin leer</span>
        @if ($noLeidas)
            <form method="POST" action="{{ route('panel.notificaciones.leer-todas') }}" class="ms-auto">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-check2-all me-1"></i> Marcar todas como leídas</button>
            </form>
        @endif
    </div>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a href="{{ route('panel.notificaciones.index') }}" @class(['nav-link', 'active' => ! $soloNoLeidas])>Todas</a></li>
        <li class="nav-item"><a href="{{ route('panel.notificaciones.index', ['no_leidas' => 1]) }}" @class(['nav-link', 'active' => $soloNoLeidas])>Sin leer</a></li>
    </ul>

    <div class="card border-0 shadow-sm">
        <div class="list-group list-group-flush">
            @forelse ($notificaciones as $notificacion)
                <a href="{{ route('panel.notificaciones.abrir', $notificacion) }}"
                   @class(['list-group-item list-group-item-action d-flex gap-3 py-3', 'bg-primary-subtle' => ! $notificacion->fecha_lectura])>
                    <i class="bi bi-{{ $notificacion->icono() }} fs-4 text-gaceta"></i>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap gap-2">
                            <strong>{{ $notificacion->titulo }}</strong>
                            @unless ($notificacion->fecha_lectura) <span class="badge text-bg-primary">Nueva</span> @endunless
                            <small class="text-body-secondary ms-auto" title="{{ $notificacion->fecha_creacion->format('d/m/Y H:i') }}">{{ $notificacion->fecha_creacion->diffForHumans() }}</small>
                        </div>
                        <div>{{ $notificacion->mensaje }}</div>
                    </div>
                </a>
            @empty
                <div class="list-group-item text-center text-body-secondary py-5">
                    <i class="bi bi-bell-slash fs-1 d-block mb-2"></i>No tiene notificaciones{{ $soloNoLeidas ? ' sin leer' : '' }}.
                </div>
            @endforelse
        </div>
        @if ($notificaciones->hasPages())
            <div class="card-footer bg-white">{{ $notificaciones->links() }}</div>
        @endif
    </div>
@endsection
