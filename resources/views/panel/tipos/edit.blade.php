@extends('layouts.panel')

@section('titulo', 'Editar tipo documental')

@section('migas')
    <li class="breadcrumb-item"><a href="{{ route('panel.tipos.index') }}">Tipos documentales</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $tipo->nombre }}</li>
@endsection

@section('contenido')
    <h1 class="h3 mb-3">{{ $tipo->nombre }}</h1>

    @if ($documentos > 0)
        <div class="alert alert-info small">
            <i class="bi bi-info-circle me-1"></i>
            Hay <strong>{{ $documentos }}</strong> documento(s) de este tipo. Si quita un campo, sus valores ya registrados
            se conservan en la base de datos pero dejan de mostrarse. Un campo nuevo obligatorio se pedirá al editar cada documento.
        </div>
    @endif

    <form method="POST" action="{{ route('panel.tipos.update', $tipo) }}" class="card border-0 shadow-sm">
        @csrf
        @method('PUT')
        <div class="card-body">
            @include('panel.tipos._form')
        </div>
        <div class="card-footer bg-body d-flex justify-content-end gap-2">
            <a href="{{ route('panel.tipos.index') }}" class="btn btn-outline-secondary">Volver</a>
            <button type="submit" class="btn btn-gaceta"><i class="bi bi-check-lg me-1"></i> Guardar cambios</button>
        </div>
    </form>
@endsection
