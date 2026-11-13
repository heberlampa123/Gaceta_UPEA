{{--
    Relaciones entre normas (13/11/2026): qué normas anteriores afecta este documento (abroga, deroga,
    modifica) y qué normas posteriores lo afectan a él. Se incluye desde show.blade.php.
    Para retirar la función basta con GACETA_RELACIONES=false en .env (esta tarjeta deja de mostrarse).
--}}
@php($emitidas = $documento->relacionesEmitidas)
@php($recibidas = $documento->relacionesRecibidas)
@php($puedeEditar = auth()->user()->can('update', $documento))

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-body"><h2 class="h6 mb-0"><i class="bi bi-diagram-3 me-1"></i> Relación con otras normas</h2></div>

    @if ($emitidas->isEmpty() && $recibidas->isEmpty() && ! $puedeEditar)
        <div class="card-body small text-body-secondary">Este documento no abroga, deroga ni modifica otras normas, y ninguna lo afecta.</div>
    @endif

    @if ($emitidas->isNotEmpty())
        <div class="card-body pb-0">
            <div class="small fw-semibold text-body-secondary mb-1">Este documento…</div>
        </div>
        <ul class="list-group list-group-flush">
            @foreach ($emitidas as $relacion)
                <li class="list-group-item small d-flex gap-2 align-items-start">
                    <div class="flex-grow-1">
                        <span @class(['badge', 'bg-danger-subtle text-danger-emphasis border border-danger-subtle' => $relacion->tipo->quita_vigencia, 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' => ! $relacion->tipo->quita_vigencia])>{{ $relacion->tipo->nombre }}</span>
                        <a href="{{ route('panel.documentos.show', $relacion->destino) }}">{{ $relacion->destino->nombreCorto() }}</a>
                        @if ($relacion->detalle) <div>{{ $relacion->detalle }}</div> @endif
                        @unless ($relacion->esEfectiva())
                            <div class="text-body-secondary"><i class="bi bi-hourglass-split"></i> Surtirá efecto cuando este documento se publique.</div>
                        @endunless
                    </div>
                    @if ($puedeEditar)
                        <form method="POST" action="{{ route('panel.documentos.relaciones.destroy', [$documento, $relacion]) }}"
                              data-confirmar="¿Quitar la relación con {{ $relacion->destino->nombreCorto() }}?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-link text-danger p-0" aria-label="Quitar relación"><i class="bi bi-x-lg"></i></button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($recibidas->isNotEmpty())
        <div class="card-body pb-0">
            <div class="small fw-semibold text-body-secondary mb-1">Este documento fue…</div>
        </div>
        <ul class="list-group list-group-flush">
            @foreach ($recibidas as $relacion)
                <li class="list-group-item small">
                    <span @class(['badge', 'bg-danger-subtle text-danger-emphasis border border-danger-subtle' => $relacion->tipo->quita_vigencia, 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' => ! $relacion->tipo->quita_vigencia])>{{ $relacion->tipo->nombre_inverso }}</span>
                    <a href="{{ route('panel.documentos.show', $relacion->origen) }}">{{ $relacion->origen->nombreCorto() }}</a>
                    @if ($relacion->detalle) <div>{{ $relacion->detalle }}</div> @endif
                    @unless ($relacion->esEfectiva())
                        <div class="text-body-secondary"><i class="bi bi-hourglass-split"></i>
                            Pendiente: esa norma está en estado «{{ $relacion->origen->estadoActual->nombre }}»; surtirá efecto al publicarse.</div>
                    @endunless
                </li>
            @endforeach
        </ul>
    @endif

    @if ($puedeEditar)
        <form method="POST" action="{{ route('panel.documentos.relaciones.store', $documento) }}" class="card-body border-top"
              data-relaciones data-url-candidatos="{{ route('panel.documentos.relaciones.candidatos', $documento) }}">
            @csrf
            <div class="small fw-semibold mb-2">Registrar norma afectada</div>
            <div class="mb-2">
                <label for="id_tipo_relacion" class="form-label small mb-1">Este documento…</label>
                <select id="id_tipo_relacion" name="id_tipo_relacion" required
                        @class(['form-select', 'form-select-sm', 'is-invalid' => $errors->has('id_tipo_relacion')])>
                    @foreach ($tiposRelacion as $tipo)
                        <option value="{{ $tipo->id_tipo_relacion }}" data-descripcion="{{ $tipo->descripcion }}" data-requiere-detalle="{{ $tipo->requiere_detalle ? 1 : 0 }}"
                                @selected(old('id_tipo_relacion') == $tipo->id_tipo_relacion)>{{ $tipo->nombre }}</option>
                    @endforeach
                </select>
                <div class="form-text" data-descripcion-tipo>{{ $tiposRelacion->first()?->descripcion }}</div>
                @error('id_tipo_relacion') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-2">
                <label for="buscar_norma" class="form-label small mb-1">Norma afectada</label>
                <input type="search" id="buscar_norma" autocomplete="off" placeholder="Número o título (ej.: 025/2026)"
                       @class(['form-control', 'form-control-sm', 'is-invalid' => $errors->has('id_documento_destino')])>
                <input type="hidden" name="id_documento_destino" value="{{ old('id_documento_destino') }}" data-destino>
                <div class="list-group mt-1 small" data-candidatos></div>
                <div class="form-text" data-seleccionada>Solo normas publicadas, vigentes y anteriores a este documento.</div>
                @error('id_documento_destino') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="mb-2">
                <label for="detalle" class="form-label small mb-1">Parte afectada <span class="text-body-secondary" data-detalle-opcional>(opcional)</span></label>
                <input type="text" id="detalle" name="detalle" value="{{ old('detalle') }}" maxlength="300" placeholder="Ej.: Artículos 3 y 5"
                       @class(['form-control', 'form-control-sm', 'is-invalid' => $errors->has('detalle')])>
                @error('detalle') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="text-end">
                <button type="submit" class="btn btn-sm btn-gaceta"><i class="bi bi-link-45deg me-1"></i> Registrar relación</button>
            </div>
        </form>
    @endif
</div>
