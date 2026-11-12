{{-- Fila de un campo adicional. Variables: $i (índice o "__INDICE__"), $campo (array) y $existente (bool: ya guardado) --}}
@php
    $opciones = $campo['opciones'] ?? [];
    $opcionesTexto = is_array($opciones) ? implode("\n", $opciones) : (string) $opciones;
    $tipoCampo = $campo['tipo'] ?? 'texto';
    $error = fn (string $clave) => is_int($i) ? $errors->first("campos.{$i}.{$clave}") : null;
@endphp

<div class="border rounded p-3 mb-2 bg-white" data-campo>
    <div class="row g-2 align-items-start">
        <div class="col-md-4">
            <label class="form-label small mb-1" for="campo-{{ $i }}-etiqueta">Etiqueta</label>
            <input type="text" id="campo-{{ $i }}-etiqueta" name="campos[{{ $i }}][etiqueta]" value="{{ $campo['etiqueta'] ?? '' }}" maxlength="100"
                   placeholder="Ej.: N.º de sesión" data-etiqueta @class(['form-control', 'form-control-sm', 'is-invalid' => $error('etiqueta')])>
            @if ($error('etiqueta')) <div class="invalid-feedback">{{ $error('etiqueta') }}</div> @endif
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1" for="campo-{{ $i }}-nombre">Nombre interno</label>
            <input type="text" id="campo-{{ $i }}-nombre" name="campos[{{ $i }}][nombre]" value="{{ $campo['nombre'] ?? '' }}" maxlength="40"
                   data-nombre @if ($existente) readonly title="No se modifica: los documentos ya registrados usan este nombre." @endif
                   @class(['form-control', 'form-control-sm', 'font-monospace', 'is-invalid' => $error('nombre')])>
            @if ($error('nombre')) <div class="invalid-feedback">{{ $error('nombre') }}</div> @endif
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1" for="campo-{{ $i }}-tipo">Tipo de dato</label>
            <select id="campo-{{ $i }}-tipo" name="campos[{{ $i }}][tipo]" data-tipo-campo @class(['form-select', 'form-select-sm', 'is-invalid' => $error('tipo')])>
                @foreach (\App\Models\TipoDocumental::TIPOS_CAMPO as $valor => $texto)
                    <option value="{{ $valor }}" @selected($tipoCampo === $valor)>{{ $texto }}</option>
                @endforeach
            </select>
            @if ($error('tipo')) <div class="invalid-feedback">{{ $error('tipo') }}</div> @endif
        </div>
        <div class="col-8 col-md-1">
            <span class="form-label small mb-1 d-block">Oblig.</span>
            <div class="form-check mt-1">
                <input type="hidden" name="campos[{{ $i }}][obligatorio]" value="0">
                <input class="form-check-input" type="checkbox" name="campos[{{ $i }}][obligatorio]" value="1" id="campo-{{ $i }}-obligatorio"
                       @checked($campo['obligatorio'] ?? false)>
                <label class="form-check-label visually-hidden" for="campo-{{ $i }}-obligatorio">Obligatorio</label>
            </div>
        </div>
        <div class="col-4 col-md-1 text-end">
            <span class="form-label small mb-1 d-block">&nbsp;</span>
            <button type="button" class="btn btn-sm btn-outline-danger" data-quitar-campo data-existente="{{ $existente ? '1' : '0' }}" aria-label="Quitar campo">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="col-12" data-opciones @if ($tipoCampo !== 'seleccion') hidden @endif>
            <label class="form-label small mb-1" for="campo-{{ $i }}-opciones">Opciones de la lista (una por línea)</label>
            <textarea id="campo-{{ $i }}-opciones" name="campos[{{ $i }}][opciones]" rows="3" placeholder="Ordinaria&#10;Extraordinaria"
                      @class(['form-control', 'form-control-sm', 'is-invalid' => $error('opciones')])>{{ $opcionesTexto }}</textarea>
            @if ($error('opciones')) <div class="invalid-feedback">{{ $error('opciones') }}</div> @endif
        </div>
    </div>
</div>
