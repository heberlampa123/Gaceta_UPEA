@extends('layouts.panel')

@section('titulo', 'Editar documento')

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('panel.documentos.index') }}">Gestión documental</a></li>
            <li class="breadcrumb-item"><a href="{{ route('panel.documentos.show', $documento) }}">{{ $documento->numero }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Editar</li>
        </ol>
    </nav>

    <h1 class="h3 mb-1">Editar datos del documento</h1>
    <p class="text-body-secondary">Para reemplazar el PDF, cargue una nueva versión desde la ficha del documento.</p>

    <form method="POST" action="{{ route('panel.documentos.update', $documento) }}" class="card border-0 shadow-sm">
        @csrf
        @method('PUT')
        <div class="card-body">
            @include('panel.documentos._form')
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('panel.documentos.show', $documento) }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-gaceta"><i class="bi bi-check-lg me-1"></i> Guardar cambios</button>
        </div>
    </form>
@endsection
