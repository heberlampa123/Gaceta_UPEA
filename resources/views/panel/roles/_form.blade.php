{{-- Campos compartidos por el alta y la edición de roles --}}
@php($seleccionados = array_map('intval', old('permisos', $asignados)))

<div class="row g-3">
    <div class="col-md-4">
        <label for="codigo" class="form-label">Código <span class="text-danger">*</span></label>
        @if ($rol->exists)
            <input type="text" id="codigo" value="{{ $rol->codigo }}" class="form-control font-monospace" disabled aria-describedby="ayudaCodigo">
            <div id="ayudaCodigo" class="form-text">El código no se modifica: el sistema lo usa como identificador estable.</div>
        @else
            <input type="text" id="codigo" name="codigo" value="{{ old('codigo') }}" maxlength="30" required
                   @class(['form-control', 'font-monospace', 'text-uppercase', 'is-invalid' => $errors->has('codigo')])
                   aria-describedby="ayudaCodigo">
            <div id="ayudaCodigo" class="form-text">Ej.: SECRETARIA. No podrá cambiarse después.</div>
            @error('codigo') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @endif
    </div>
    <div class="col-md-8">
        <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
        <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $rol->nombre) }}" maxlength="80" required
               @class(['form-control', 'is-invalid' => $errors->has('nombre')])>
        @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label for="descripcion" class="form-label">Descripción</label>
        <input type="text" id="descripcion" name="descripcion" value="{{ old('descripcion', $rol->descripcion) }}" maxlength="255"
               @class(['form-control', 'is-invalid' => $errors->has('descripcion')])>
        @error('descripcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="activo" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1"
                   @checked(old('activo', $rol->activo ?? true))>
            <label class="form-check-label" for="activo">Rol activo</label>
        </div>
        @error('activo') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <h2 class="h6 mt-2">Permisos</h2>
        @error('permisos') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
        <div class="row g-3">
            @foreach ($permisosPorModulo as $modulo => $permisos)
                <div class="col-md-6 col-xl-4">
                    <fieldset class="border rounded p-3 h-100 bg-white">
                        <legend class="float-none w-auto px-2 fs-6 text-capitalize fw-semibold mb-0">{{ $modulo }}</legend>
                        @foreach ($permisos as $permiso)
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="permisos[]" value="{{ $permiso->id_permiso }}" id="permiso{{ $permiso->id_permiso }}"
                                       @checked(in_array($permiso->id_permiso, $seleccionados))>
                                <label class="form-check-label" for="permiso{{ $permiso->id_permiso }}">
                                    {{ $permiso->descripcion }}
                                    <small class="d-block text-body-secondary font-monospace">{{ $permiso->codigo }}</small>
                                </label>
                            </div>
                        @endforeach
                    </fieldset>
                </div>
            @endforeach
        </div>
    </div>
</div>
