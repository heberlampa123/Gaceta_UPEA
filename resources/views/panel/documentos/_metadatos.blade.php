{{--
    Campos adicionales de cada tipo documental (Fase 4).
    Se generan los campos de todos los tipos; solo el del tipo elegido queda visible y habilitado
    (los <fieldset disabled> no se envían). resources/js/app.js los alterna al cambiar el tipo.
--}}
@php
    $tipoElegido = (int) old('id_tipo_documental', $documento->id_tipo_documental);
@endphp

@foreach ($tipos as $tipo)
    @continue($tipo->campos() === [])
    @php
        $activo = $tipoElegido === $tipo->id_tipo_documental;
    @endphp

    <fieldset class="col-12 border rounded p-3" data-campos-tipo="{{ $tipo->id_tipo_documental }}" @unless ($activo) hidden disabled @endunless>
        <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">Datos propios de «{{ $tipo->nombre }}»</legend>
        <div class="row g-3 mt-0">
            @foreach ($tipo->campos() as $campo)
                @php
                    $clave = "metadatos.{$campo['nombre']}";
                    $id = "meta-{$tipo->id_tipo_documental}-{$campo['nombre']}";
                    $guardado = $documento->id_tipo_documental === $tipo->id_tipo_documental ? ($documento->metadatos[$campo['nombre']] ?? null) : null;
                    $valor = old($clave, $guardado);
                    $nombreHtml = "metadatos[{$campo['nombre']}]";
                    $obligatorio = $campo['obligatorio'] && $campo['tipo'] !== 'si_no';
                @endphp

                <div @class(['col-12' => $campo['tipo'] === 'texto_largo', 'col-md-6' => $campo['tipo'] !== 'texto_largo'])>
                    @if ($campo['tipo'] === 'si_no')
                        <div class="form-check form-switch mt-md-4">
                            <input type="hidden" name="{{ $nombreHtml }}" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="{{ $id }}" name="{{ $nombreHtml }}" value="1" @checked(filter_var($valor, FILTER_VALIDATE_BOOLEAN))>
                            <label class="form-check-label" for="{{ $id }}">{{ $campo['etiqueta'] }}</label>
                        </div>
                    @else
                        <label for="{{ $id }}" class="form-label">{{ $campo['etiqueta'] }} @if ($obligatorio) <span class="text-danger">*</span> @endif</label>

                        @switch($campo['tipo'])
                            @case('texto_largo')
                                <textarea id="{{ $id }}" name="{{ $nombreHtml }}" rows="3" maxlength="5000" @required($obligatorio)
                                          @class(['form-control', 'is-invalid' => $errors->has($clave)])>{{ $valor }}</textarea>
                                @break
                            @case('numero')
                                <input type="number" step="any" id="{{ $id }}" name="{{ $nombreHtml }}" value="{{ $valor }}" @required($obligatorio)
                                       @class(['form-control', 'is-invalid' => $errors->has($clave)])>
                                @break
                            @case('fecha')
                                <input type="date" id="{{ $id }}" name="{{ $nombreHtml }}" value="{{ $valor }}" @required($obligatorio)
                                       @class(['form-control', 'is-invalid' => $errors->has($clave)])>
                                @break
                            @case('seleccion')
                                <select id="{{ $id }}" name="{{ $nombreHtml }}" @required($obligatorio) @class(['form-select', 'is-invalid' => $errors->has($clave)])>
                                    <option value="">— Seleccione —</option>
                                    @foreach ($campo['opciones'] as $opcion)
                                        <option value="{{ $opcion }}" @selected($valor === $opcion)>{{ $opcion }}</option>
                                    @endforeach
                                </select>
                                @break
                            @default
                                <input type="text" id="{{ $id }}" name="{{ $nombreHtml }}" value="{{ $valor }}" maxlength="255" @required($obligatorio)
                                       @class(['form-control', 'is-invalid' => $errors->has($clave)])>
                        @endswitch
                    @endif

                    @error($clave) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            @endforeach
        </div>
    </fieldset>
@endforeach
