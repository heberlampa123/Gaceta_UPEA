@extends('layouts.panel')

@section('titulo', 'Nuevo tipo documental')

@section('migas')
    <li class="breadcrumb-item"><a href="{{ route('panel.tipos.index') }}">Tipos documentales</a></li>
    <li class="breadcrumb-item active" aria-current="page">Nuevo</li>
@endsection

@section('contenido')
    <h1 class="h3 mb-3">Nuevo tipo documental</h1>

    <form method="POST" action="{{ route('panel.tipos.store') }}" class="card border-0 shadow-sm">
        @csrf
        <div class="card-body">
            @include('panel.tipos._form')
        </div>
        <div class="card-footer bg-body d-flex justify-content-end gap-2">
            <a href="{{ route('panel.tipos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-gaceta"><i class="bi bi-check-lg me-1"></i> Crear tipo</button>
        </div>
    </form>
@endsection
