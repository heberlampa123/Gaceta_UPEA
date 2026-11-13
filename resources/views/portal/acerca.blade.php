@extends('layouts.portal')

@section('titulo', 'Acerca de la Gaceta')

{{-- Texto informativo general: la Gaceta Universitaria puede ajustarlo a su redacción oficial. --}}
@section('contenido')
    <section class="portal-heroe">
        <div class="container py-4 py-lg-5">
            <h1 class="display-6 fw-bold mb-2">Acerca de la Gaceta Universitaria</h1>
            <p class="lead text-body-secondary mb-0" style="max-width: 48rem">
                El medio oficial de la Universidad Pública de El Alto para publicar y difundir sus documentos normativos.
            </p>
        </div>
    </section>

    <div class="container py-4">
        <div class="row g-4">
            @foreach ([
                ['journal-text', '¿Qué publica?', 'Las resoluciones del Honorable Consejo Universitario y, progresivamente, otros documentos oficiales de la Universidad.'],
                ['shield-check', 'Documentos auténticos', 'Cada documento se revisa y aprueba antes de publicarse. Su archivo PDF tiene una huella digital (SHA-256) que permite comprobar que una copia no fue modificada.'],
                ['search', 'Consulta libre', 'Cualquier persona puede buscar, ver y descargar los documentos vigentes, sin crear una cuenta.'],
            ] as [$icono, $titulo, $texto])
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <span class="icono-cuadro mb-3"><i class="bi bi-{{ $icono }}"></i></span>
                            <h2 class="h5">{{ $titulo }}</h2>
                            <p class="text-body-secondary mb-0">{{ $texto }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <h2 class="h5">¿Cómo verificar un documento descargado?</h2>
                <ol class="text-body-secondary mb-3">
                    <li>Abra la ficha del documento en este portal y copie su huella digital SHA-256.</li>
                    <li>Calcule la huella del archivo descargado (en Windows: <code>Get-FileHash archivo.pdf -Algorithm SHA256</code> en PowerShell).</li>
                    <li>Si ambas huellas coinciden, el archivo es idéntico al publicado por la Gaceta.</li>
                </ol>
                <a href="{{ route('portal.inicio') }}" class="btn btn-gaceta"><i class="bi bi-search me-1"></i> Buscar documentos</a>
            </div>
        </div>
    </div>
@endsection
