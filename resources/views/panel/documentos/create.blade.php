@extends('layouts.panel')

@section('titulo', 'Registrar documento')

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('panel.documentos.index') }}">Gestión documental</a></li>
            <li class="breadcrumb-item active" aria-current="page">Registrar</li>
        </ol>
    </nav>

    <h1 class="h3 mb-1">Registrar documento</h1>
    <p class="text-body-secondary">El documento se guardará en estado <strong>Borrador</strong>. Podrá enviarlo a revisión cuando esté completo.</p>

    <form method="POST" action="{{ route('panel.documentos.store') }}" enctype="multipart/form-data" class="card border-0 shadow-sm">
        @csrf
        <div class="card-body">
            @include('panel.documentos._form')
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('panel.documentos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-gaceta"><i class="bi bi-check-lg me-1"></i> Registrar</button>
        </div>
    </form>
@endsection
