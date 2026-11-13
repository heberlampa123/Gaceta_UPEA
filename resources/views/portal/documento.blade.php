@extends('layouts.portal')

@section('titulo', $documento->nombreCorto())

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('portal.inicio') }}">Documentos</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $documento->numero }}</li>
        </ol>
    </nav>

    <div class="mb-3">
        <span class="badge text-bg-light border">{{ $documento->tipoDocumental->nombre }}</span>
        <h1 class="h3 mt-1 mb-1">{{ $documento->nombreCorto() }}</h1>
        <p class="lead mb-0">{{ $documento->titulo }}</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                    <h2 class="h6 mb-0"><i class="bi bi-file-earmark-pdf text-danger me-1"></i> Documento</h2>
                    <div class="ms-auto d-flex gap-2">
                        <a href="{{ route('portal.documentos.pdf', $documento) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-box-arrow-up-right"></i> Abrir en pestaña nueva
                        </a>
                        <a href="{{ route('portal.documentos.pdf', [$documento, 'descargar' => 1]) }}" class="btn btn-sm btn-gaceta">
                            <i class="bi bi-download"></i> Descargar PDF
                        </a>
                    </div>
                </div>
                <iframe src="{{ route('portal.documentos.pdf', $documento) }}" title="PDF de {{ $documento->nombreCorto() }}"
                        class="w-100 border-0 d-block" style="height: 80vh"></iframe>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Datos del documento</h2></div>
                <dl class="card-body mb-0">
                    <dt class="small text-body-secondary">Número</dt><dd class="font-monospace">{{ $documento->numero }}</dd>
                    <dt class="small text-body-secondary">Gestión</dt><dd>{{ $documento->gestion }}</dd>
                    <dt class="small text-body-secondary">Fecha de emisión</dt><dd>{{ $documento->fecha_emision->format('d/m/Y') }}</dd>
                    <dt class="small text-body-secondary">Instancia emisora</dt><dd>{{ $documento->instancia_emisora ?? '—' }}</dd>
                    @foreach ($documento->tipoDocumental->campos() as $campo)
                        <dt class="small text-body-secondary">{{ $campo['etiqueta'] }}</dt>
                        <dd>{{ \App\Models\TipoDocumental::formatearValor($campo, $documento->metadatos[$campo['nombre']] ?? null) }}</dd>
                    @endforeach
                    @if ($documento->descripcion)
                        <dt class="small text-body-secondary">Descripción</dt><dd>{!! nl2br(e($documento->descripcion)) !!}</dd>
                    @endif
                    <dt class="small text-body-secondary">Publicado en la Gaceta</dt><dd class="mb-0">{{ $documento->fecha_publicacion?->format('d/m/Y') }}</dd>
                </dl>
            </div>

            @if (\App\Models\Documento::relacionesHabilitadas())
                @include('portal._relaciones')
            @endif

            @if ($documento->versionVigente)
                <div class="card border-0 shadow-sm">
                    <div class="card-body small">
                        <h2 class="h6"><i class="bi bi-shield-check text-success me-1"></i> Verificación del archivo</h2>
                        <p class="text-body-secondary mb-1">Huella digital SHA-256 del PDF publicado. Permite comprobar que una copia descargada no fue alterada.</p>
                        <code class="d-block text-break user-select-all">{{ $documento->versionVigente->hash_sha256 }}</code>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
