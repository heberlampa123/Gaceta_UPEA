{{-- Campos compartidos por el alta y la edición de tipos documentales --}}
@php
    $campos = old('campos') !== null ? array_values(old('campos')) : $tipo->campos();
    $nombresGuardados = collect($tipo->campos())->pluck('nombre')->all();
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label for="codigo" class="form-label">Código <span class="text-danger">*</span></label>
        @if ($tipo->exists)
            <input type="text" id="codigo" value="{{ $tipo->codigo }}" class="form-control font-monospace" disabled aria-describedby="ayudaCodigo">
            <div id="ayudaCodigo" class="form-text">El código no se modifica: el sistema lo usa como identificador estable.</div>
        @else
            <input type="text" id="codigo" name="codigo" value="{{ old('codigo') }}" maxlength="30" required
                   @class(['form-control', 'font-monospace', 'text-uppercase', 'is-invalid' => $errors->has('codigo')]) aria-describedby="ayudaCodigo">
            <div id="ayudaCodigo" class="form-text">Ej.: RES_RECTORAL. No podrá cambiarse después.</div>
            @error('codigo') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @endif
    </div>
    <div class="col-md-8">
        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
        <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $tipo->nombre) }}" maxlength="100" required
               placeholder="Ej.: Resolución Rectoral" @class(['form-control', 'is-invalid' => $errors->has('nombre')])>
        @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label for="descripcion" class="form-label">Descripción</label>
        <input type="text" id="descripcion" name="descripcion" value="{{ old('descripcion', $tipo->descripcion) }}" maxlength="255"
               @class(['form-control', 'is-invalid' => $errors->has('descripcion')])>
        @error('descripcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="activo" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1" @checked(old('activo', $tipo->activo ?? true))>
            <label class="form-check-label" for="activo">Tipo activo <small class="text-body-secondary">(los inactivos no se ofrecen al registrar documentos)</small></label>
        </div>
    </div>

    <div class="col-12">
        <h2 class="h6 mt-2 mb-1">Campos adicionales</h2>
        <p class="small text-body-secondary mb-2">
            Datos propios de este tipo que se pedirán al registrar cada documento, además de los datos comunes
            (número, gestión, fecha, título, descripción e instancia emisora).
        </p>
        @error('campos') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

        <div data-campos data-siguiente="{{ count($campos) }}">
            @foreach ($campos as $i => $campo)
                @include('panel.tipos._campo', ['i' => $i, 'campo' => $campo, 'existente' => in_array($campo['nombre'] ?? null, $nombresGuardados, true)])
            @endforeach
        </div>

        <p class="small text-body-secondary fst-italic" data-campos-vacio @if (count($campos)) hidden @endif>Este tipo no tiene campos adicionales.</p>

        <button type="button" class="btn btn-sm btn-outline-primary" data-agregar-campo>
            <i class="bi bi-plus-lg me-1"></i> Agregar campo
        </button>

        <template id="plantillaCampo">
            @include('panel.tipos._campo', ['i' => '__INDICE__', 'campo' => [], 'existente' => false])
        </template>
    </div>
</div>
