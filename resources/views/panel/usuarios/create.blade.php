@extends('layouts.panel')

@section('titulo', 'Nuevo usuario')

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('panel.usuarios.index') }}">Usuarios</a></li>
            <li class="breadcrumb-item active" aria-current="page">Nuevo</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">Nuevo usuario</h1>

    <form method="POST" action="{{ route('panel.usuarios.store') }}" class="card border-0 shadow-sm">
        @csrf
        <div class="card-body">
            @include('panel.usuarios._form')
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('panel.usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-gaceta"><i class="bi bi-check-lg me-1"></i> Crear usuario</button>
        </div>
    </form>
@endsection
