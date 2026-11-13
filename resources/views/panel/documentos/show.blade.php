@extends('layouts.panel')

@section('titulo', $documento->numero)

@use('App\Support\LimitesArchivo')
@php($vigente = $documento->versionVigente)

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('panel.documentos.index') }}">Gestión documental</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $documento->numero }}</li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap align-items-start gap-2 mb-3">
        <div>
            <div class="mb-1">
                @include('panel.documentos._estado', ['estado' => $documento->estadoActual])
                <span class="badge text-bg-light border">{{ $documento->tipoDocumental->nombre }}</span>
            </div>
            <h1 class="h4 mb-0">{{ $documento->nombreCorto() }}</h1>
            <p class="text-body-secondary mb-0">{{ $documento->titulo }}</p>
        </div>
        @can('update', $documento)
            <a href="{{ route('panel.documentos.edit', $documento) }}" class="btn btn-outline-primary ms-auto">
                <i class="bi bi-pencil me-1"></i> Editar datos
            </a>
        @endcan
    </div>

    @unless ($documento->esEditable())
        <div class="alert alert-light border small">
            <i class="bi bi-lock me-1"></i>
            En estado «{{ $documento->estadoActual->nombre }}» no se pueden modificar los datos ni cargar nuevas versiones.
        </div>
    @endunless

    <div class="row g-3">
        {{-- Columna principal: PDF vigente y datos --}}
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                    <h2 class="h6 mb-0">
                        <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                        Versión vigente @if ($vigente) (v{{ $vigente->numero_version }}) @endif
                    </h2>
                    @if ($vigente)
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('panel.documentos.versiones.archivo', [$documento, $vigente]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-box-arrow-up-right"></i> Abrir
                            </a>
                            <a href="{{ route('panel.documentos.versiones.archivo', [$documento, $vigente]) }}?descargar=1" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-download"></i> Descargar
                            </a>
                        </div>
                    @endif
                </div>
                @if ($vigente)
                    <iframe src="{{ route('panel.documentos.versiones.archivo', [$documento, $vigente]) }}" title="PDF de {{ $documento->nombreCorto() }}"
                            class="w-100 border-0 d-block" style="height: 70vh"></iframe>
                @else
                    <div class="card-body text-body-secondary">Este documento no tiene un archivo PDF vigente.</div>
                @endif
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Datos del documento</h2></div>
                <dl class="row card-body mb-0">
                    <dt class="col-sm-4">Número</dt><dd class="col-sm-8 font-monospace">{{ $documento->numero }}</dd>
                    <dt class="col-sm-4">Gestión</dt><dd class="col-sm-8">{{ $documento->gestion }}</dd>
                    <dt class="col-sm-4">Fecha de emisión</dt><dd class="col-sm-8">{{ $documento->fecha_emision->format('d/m/Y') }}</dd>
                    <dt class="col-sm-4">Instancia emisora</dt><dd class="col-sm-8">{{ $documento->instancia_emisora ?? '—' }}</dd>
                    <dt class="col-sm-4">Descripción</dt><dd class="col-sm-8">{!! nl2br(e($documento->descripcion ?? '—')) !!}</dd>
                    @foreach ($documento->tipoDocumental->campos() as $campo)
                        <dt class="col-sm-4">{{ $campo['etiqueta'] }}</dt>
                        <dd class="col-sm-8">{{ \App\Models\TipoDocumental::formatearValor($campo, $documento->metadatos[$campo['nombre']] ?? null) }}</dd>
                    @endforeach
                    <dt class="col-sm-4">Registrado por</dt><dd class="col-sm-8">{{ $documento->usuarioRegistro->nombre_completo }} · {{ $documento->fecha_registro->format('d/m/Y H:i') }}</dd>
                    <dt class="col-sm-4">Última modificación</dt><dd class="col-sm-8">{{ $documento->fecha_actualizacion->format('d/m/Y H:i') }}</dd>
                    @if ($documento->fecha_publicacion)
                        <dt class="col-sm-4">Publicado</dt><dd class="col-sm-8">{{ $documento->fecha_publicacion->format('d/m/Y H:i') }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Columna lateral: acciones del flujo, versiones, observaciones e historial --}}
        <div class="col-xl-4">
            @include('panel.documentos._flujo')
            @can('agregarVersion', $documento)
                <form method="POST" action="{{ route('panel.documentos.versiones.store', $documento) }}" enctype="multipart/form-data" class="card border-0 shadow-sm mb-3">
                    @csrf
                    <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-upload me-1"></i> Cargar nueva versión</h2></div>
                    <div class="card-body">
                        <div class="mb-2">
                            <label for="archivo" class="form-label small">Archivo PDF</label>
                            <input type="file" id="archivo" name="archivo" accept="application/pdf,.pdf" required
                                   @class(['form-control', 'form-control-sm', 'is-invalid' => $errors->has('archivo')])>
                            <div class="form-text">Máximo {{ LimitesArchivo::pdfMaxEfectivoMb() }} MB. La versión actual se conserva.</div>
                            @error('archivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label for="motivo_cambio" class="form-label small">Motivo del cambio</label>
                            <input type="text" id="motivo_cambio" name="motivo_cambio" value="{{ old('motivo_cambio') }}" maxlength="255" required
                                   placeholder="Ej.: Corrección del número en el encabezado"
                                   @class(['form-control', 'form-control-sm', 'is-invalid' => $errors->has('motivo_cambio')])>
                            @error('motivo_cambio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="card-footer bg-white text-end">
                        <button type="submit" class="btn btn-sm btn-gaceta"><i class="bi bi-cloud-upload me-1"></i> Cargar versión</button>
                    </div>
                </form>
            @endcan

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-layers me-1"></i> Versiones del PDF</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($documento->versiones as $version)
                        <li class="list-group-item">
                            <div class="d-flex align-items-center gap-2">
                                <strong>v{{ $version->numero_version }}</strong>
                                @if ($version->es_vigente)
                                    <span class="badge text-bg-success">Vigente</span>
                                @endif
                                <small class="text-body-secondary ms-auto">{{ $version->fecha_carga->format('d/m/Y H:i') }}</small>
                            </div>
                            <div class="small">{{ $version->motivo_cambio }}</div>
                            <div class="small text-body-secondary">
                                {{ $version->usuarioCarga->nombre_completo }} · {{ LimitesArchivo::legible($version->tamano_bytes) }}
                            </div>
                            <div class="small text-body-secondary font-monospace text-truncate" title="SHA-256: {{ $version->hash_sha256 }}">
                                SHA-256: {{ substr($version->hash_sha256, 0, 16) }}…
                            </div>
                            <div class="d-flex gap-1 mt-1">
                                <a href="{{ route('panel.documentos.versiones.archivo', [$documento, $version]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-link px-1">Ver</a>
                                <a href="{{ route('panel.documentos.versiones.archivo', [$documento, $version]) }}?descargar=1" class="btn btn-sm btn-link px-1">Descargar</a>
                                <form method="POST" action="{{ route('panel.documentos.versiones.verificar', [$documento, $version]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-link px-1">Verificar integridad</button>
                                </form>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">Sin versiones cargadas.</li>
                    @endforelse
                </ul>
            </div>

            @include('panel.documentos._observaciones')

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-clock-history me-1"></i> Seguimiento</h2></div>
                <ol class="list-group list-group-flush list-group-numbered">
                    @forelse ($documento->seguimientos as $movimiento)
                        <li class="list-group-item d-flex gap-2">
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">{{ $movimiento->etiqueta() }}
                                    · @include('panel.documentos._estado', ['estado' => $movimiento->estadoNuevo])</div>
                                @if ($movimiento->comentario)
                                    <div class="small">{{ $movimiento->comentario }}</div>
                                @endif
                                <div class="small text-body-secondary">{{ $movimiento->usuario->nombre_completo }} · {{ $movimiento->fecha->format('d/m/Y H:i') }}</div>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">Sin movimientos registrados.</li>
                    @endforelse
                </ol>
            </div>
        </div>
    </div>
@endsection
