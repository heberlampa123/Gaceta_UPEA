{{-- Historial de observaciones del documento, agrupadas por la versión revisada (4.4.12) --}}
@if ($documento->observaciones->isNotEmpty())
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-body d-flex align-items-center">
            <h2 class="h6 mb-0"><i class="bi bi-chat-left-text me-1"></i> Observaciones</h2>
            @php
                $pendientesTotal = $documento->observaciones->whereNull('fecha_atencion')->count();
            @endphp
            @if ($pendientesTotal)
                <span class="badge text-bg-danger ms-auto">{{ $pendientesTotal }} pendiente(s)</span>
            @endif
        </div>
        <ul class="list-group list-group-flush">
            @foreach ($documento->observaciones->sortByDesc('fecha_registro') as $observacion)
                <li class="list-group-item small">
                    <div class="d-flex gap-2">
                        @if ($observacion->estaAtendida())
                            <i class="bi bi-check-circle-fill text-success" title="Atendida"></i>
                        @else
                            <i class="bi bi-exclamation-circle-fill text-danger" title="Pendiente"></i>
                        @endif
                        <div class="flex-grow-1">
                            <div>{{ $observacion->descripcion }}</div>
                            <div class="text-body-secondary">
                                {{ $observacion->revisor->nombre_completo }} · v{{ $observacion->version->numero_version }} · {{ $observacion->fecha_registro->format('d/m/Y H:i') }}
                            </div>
                            @if ($observacion->estaAtendida())
                                <div class="mt-1 ps-2 border-start border-2 border-success">
                                    <span class="fw-semibold">Respuesta:</span> {{ $observacion->respuesta }}
                                    <div class="text-body-secondary">{{ $observacion->usuarioAtiende->nombre_completo }} · {{ $observacion->fecha_atencion->format('d/m/Y H:i') }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif
