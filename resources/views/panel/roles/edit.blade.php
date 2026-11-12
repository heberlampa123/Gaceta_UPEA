@extends('layouts.panel')

@section('titulo', 'Editar rol')

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('panel.roles.index') }}">Roles y permisos</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $rol->nombre }}</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">{{ $rol->nombre }}</h1>

    @if ($rol->codigo === \App\Models\Rol::ADMIN)
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-1"></i>
            El rol Administrador no puede desactivarse y conserva siempre los permisos de gestión de usuarios y roles.
        </div>
    @endif

    <form method="POST" action="{{ route('panel.roles.update', $rol) }}" class="card border-0 shadow-sm">
        @csrf
        @method('PUT')
        <div class="card-body">
            @include('panel.roles._form')
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('panel.roles.index') }}" class="btn btn-outline-secondary">Volver</a>
            <button type="submit" class="btn btn-gaceta"><i class="bi bi-check-lg me-1"></i> Guardar cambios</button>
        </div>
    </form>
@endsection
