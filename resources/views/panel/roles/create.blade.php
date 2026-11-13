@extends('layouts.panel')

@section('titulo', 'Nuevo rol')

@section('migas')
    <li class="breadcrumb-item"><a href="{{ route('panel.roles.index') }}">Roles y permisos</a></li>
    <li class="breadcrumb-item active" aria-current="page">Nuevo</li>
@endsection

@section('contenido')
    <h1 class="h3 mb-3">Nuevo rol</h1>

    <form method="POST" action="{{ route('panel.roles.store') }}" class="card border-0 shadow-sm">
        @csrf
        <div class="card-body">
            @include('panel.roles._form')
        </div>
        <div class="card-footer bg-body d-flex justify-content-end gap-2">
            <a href="{{ route('panel.roles.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-gaceta"><i class="bi bi-check-lg me-1"></i> Crear rol</button>
        </div>
    </form>
@endsection
