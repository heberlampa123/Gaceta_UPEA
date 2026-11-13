@extends('layouts.panel')

@section('titulo', 'Revisión y publicación')

@section('contenido')
    <h1 class="h3 mb-1">Revisión y publicación</h1>
    <p class="text-body-secondary small">
        Documentos enviados por los encargados. No se muestran los que usted registró, porque no puede revisarlos.
    </p>

    <ul class="nav nav-tabs mb-3">
        @foreach (\App\Http\Controllers\Panel\RevisionController::BANDEJAS as $clave => [$titulo])
            <li class="nav-item">
                <a href="{{ route('panel.revision.index', ['bandeja' => $clave]) }}"
                   @class(['nav-link', 'active' => $bandeja === $clave]) @if ($bandeja === $clave) aria-current="page" @endif>
                    {{ $titulo }}
                    <span @class(['badge rounded-pill ms-1', 'text-bg-primary' => $conteos[$clave] > 0, 'text-bg-light border' => $conteos[$clave] === 0])>{{ $conteos[$clave] }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Número</th>
                        <th>Título</th>
                        <th>Registrado por</th>
                        <th>Versión</th>
                        <th>En este estado desde</th>
                        <th class="text-end">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documentos as $documento)
                        <tr>
                            <td class="text-nowrap">
                                <div class="fw-semibold font-monospace">{{ $documento->numero }}</div>
                                <small class="text-body-secondary">{{ $documento->tipoDocumental->nombre }}</small>
                            </td>
                            <td style="min-width: 260px">
                                {{ \Illuminate\Support\Str::limit($documento->titulo, 110) }}
                                @if ($documento->observaciones_pendientes_count)
                                    <span class="badge text-bg-danger">{{ $documento->observaciones_pendientes_count }} observación(es) pendiente(s)</span>
                                @endif
                            </td>
                            <td><small>{{ $documento->usuarioRegistro->nombre_completo }}</small></td>
                            <td>v{{ $documento->versionVigente?->numero_version }}</td>
                            <td class="text-nowrap"><small>{{ $documento->fecha_actualizacion->format('d/m/Y H:i') }}</small></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('panel.documentos.show', $documento) }}" class="btn btn-sm btn-gaceta">
                                    @switch($bandeja)
                                        @case('pendientes') <i class="bi bi-search me-1"></i> Revisar @break
                                        @case('aprobados') <i class="bi bi-globe me-1"></i> Publicar @break
                                        @default <i class="bi bi-eye me-1"></i> Ver
                                    @endswitch
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">No hay documentos en esta bandeja.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($documentos->hasPages())
            <div class="card-footer bg-white">{{ $documentos->links() }}</div>
        @endif
    </div>
@endsection
