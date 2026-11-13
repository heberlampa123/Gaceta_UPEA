{{--
    Acciones del flujo de revisión y publicación (Fase 5).
    Cada bloque aparece solo si DocumentoPolicy lo permite (permiso + estado + revisor distinto).
--}}
@php
    $pendientes = $documento->observaciones->whereNull('fecha_atencion');
    $codigo = $documento->estadoActual->codigo;
    $esRevisorDelEstado = in_array($codigo, ['PENDIENTE_REVISION', 'APROBADO'], true)
        && (auth()->user()->can('documento.revisar') || auth()->user()->can('documento.publicar'));
@endphp

{{-- Borrador → Pendiente de revisión --}}
@can('enviarARevision', $documento)
    <form method="POST" action="{{ route('panel.documentos.enviar', $documento) }}" class="card border-0 shadow-sm mb-3 border-start border-4 border-primary"
          data-confirmar="Una vez enviado, no podrá modificar el documento hasta que el revisor lo devuelva. ¿Enviar a revisión?">
        @csrf
        <div class="card-body">
            <h2 class="h6"><i class="bi bi-send me-1"></i> Enviar a revisión</h2>
            <p class="small text-body-secondary mb-2">Verifique los datos y el PDF antes de enviarlo.</p>
            <input type="text" name="comentario" maxlength="300" class="form-control form-control-sm mb-2" placeholder="Comentario para el revisor (opcional)" aria-label="Comentario para el revisor">
            <button type="submit" class="btn btn-sm btn-gaceta w-100"><i class="bi bi-send me-1"></i> Enviar a revisión</button>
        </div>
    </form>
@endcan

{{-- Observado → Pendiente de revisión (atender observaciones) --}}
@can('atenderObservaciones', $documento)
    <form method="POST" action="{{ route('panel.documentos.reenviar', $documento) }}" class="card border-0 shadow-sm mb-3 border-start border-4 border-danger">
        @csrf
        <div class="card-body">
            <h2 class="h6"><i class="bi bi-reply me-1"></i> Atender observaciones</h2>
            <p class="small text-body-secondary mb-2">
                Si el PDF requiere correcciones, cargue primero la nueva versión. Luego describa cómo atendió cada observación y reenvíe.
            </p>
            @forelse ($pendientes as $observacion)
                <div class="mb-2">
                    <label for="respuesta{{ $observacion->id_observacion }}" class="form-label small mb-1">
                        <i class="bi bi-exclamation-circle text-danger"></i> {{ $observacion->descripcion }}
                    </label>
                    <textarea id="respuesta{{ $observacion->id_observacion }}" name="respuestas[{{ $observacion->id_observacion }}]" rows="2" maxlength="2000" required
                              placeholder="Ej.: Se corrigió el número en el encabezado (versión 2)."
                              @class(['form-control', 'form-control-sm', 'is-invalid' => $errors->has('respuestas.'.$observacion->id_observacion)])>{{ old('respuestas.'.$observacion->id_observacion) }}</textarea>
                    @error('respuestas.'.$observacion->id_observacion) <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @empty
                <p class="small">No hay observaciones pendientes.</p>
            @endforelse
            <input type="text" name="comentario" maxlength="300" class="form-control form-control-sm mb-2" placeholder="Comentario general (opcional)" aria-label="Comentario general">
            <button type="submit" class="btn btn-sm btn-gaceta w-100"><i class="bi bi-arrow-repeat me-1"></i> Reenviar a revisión</button>
        </div>
    </form>
@endcan

{{-- Pendiente de revisión → Observado / Aprobado --}}
@if (auth()->user()->can('devolver', $documento) || auth()->user()->can('aprobar', $documento))
    <div class="card border-0 shadow-sm mb-3 border-start border-4 border-warning">
        <div class="card-body">
            <h2 class="h6"><i class="bi bi-clipboard-check me-1"></i> Revisión</h2>
            <p class="small text-body-secondary">Revise los datos y el PDF vigente (v{{ $documento->versionVigente?->numero_version }}).</p>

            @can('aprobar', $documento)
                <form method="POST" action="{{ route('panel.documentos.aprobar', $documento) }}" class="mb-3"
                      data-confirmar="¿Autorizar este documento para su publicación?">
                    @csrf
                    <input type="text" name="comentario" maxlength="300" class="form-control form-control-sm mb-2" placeholder="Comentario (opcional)" aria-label="Comentario de aprobación">
                    <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-check2-circle me-1"></i> Aprobar para publicación</button>
                </form>
            @endcan

            @can('devolver', $documento)
                <form method="POST" action="{{ route('panel.documentos.devolver', $documento) }}" class="border-top pt-3">
                    @csrf
                    <h3 class="h6 small fw-semibold">Devolver con observaciones</h3>
                    @php
                        $anteriores = old('observaciones', ['']);
                    @endphp
                    <div data-observaciones>
                        @foreach ($anteriores as $i => $texto)
                            <textarea name="observaciones[]" rows="2" maxlength="2000" class="form-control form-control-sm mb-2"
                                      placeholder="Describa la observación" aria-label="Observación">{{ $texto }}</textarea>
                        @endforeach
                    </div>
                    @error('observaciones') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <button type="button" class="btn btn-sm btn-link px-0 mb-2" data-agregar-observacion><i class="bi bi-plus-lg"></i> Agregar otra observación</button>
                    <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-arrow-return-left me-1"></i> Devolver para corrección</button>
                </form>
            @endcan
        </div>
    </div>
@endif

{{-- Aprobado → Publicado --}}
@can('publicar', $documento)
    <form method="POST" action="{{ route('panel.documentos.publicar', $documento) }}" class="card border-0 shadow-sm mb-3 border-start border-4 border-success"
          data-confirmar="El documento quedará visible para cualquier persona en el portal público. ¿Publicar ahora?">
        @csrf
        <div class="card-body">
            <h2 class="h6"><i class="bi bi-globe me-1"></i> Publicación</h2>
            <p class="small text-body-secondary mb-2">El documento está aprobado. Al publicarlo, quedará disponible en el portal público.</p>
            <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-globe me-1"></i> Publicar en el portal</button>
        </div>
    </form>
@endcan

{{-- Aviso cuando el revisor no puede actuar porque él registró el documento --}}
@if ($esRevisorDelEstado && $documento->id_usuario_registro === auth()->id())
    <div class="alert alert-warning small">
        <i class="bi bi-person-exclamation me-1"></i> Usted registró este documento: la revisión y la publicación deben realizarlas otro revisor.
    </div>
@endif
