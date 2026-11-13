@extends('layouts.base')

{{-- Plantilla común de las páginas de error (sin consultas a la base de datos). --}}
@section('titulo', $titulo)
@section('clase_body', 'd-flex align-items-center justify-content-center min-vh-100 p-3')

@section('cuerpo')
    <main class="card border-0 shadow-sm text-center" style="max-width: 32rem">
        <div class="card-body p-5">
            <i class="bi bi-{{ $icono }} text-gaceta" style="font-size: 3.5rem"></i>
            <p class="text-body-secondary mb-1 mt-2">Error {{ $codigo }}</p>
            <h1 class="h4">{{ $titulo }}</h1>
            <p class="text-body-secondary">{{ $mensaje }}</p>
            <div class="d-flex flex-wrap gap-2 justify-content-center mt-4">
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </a>
                <a href="{{ url('/') }}" class="btn btn-gaceta"><i class="bi bi-house me-1"></i> Ir al portal</a>
            </div>
        </div>
        <div class="card-footer bg-transparent small text-body-secondary py-3">Gaceta Universitaria · Universidad Pública de El Alto</div>
    </main>
@endsection
