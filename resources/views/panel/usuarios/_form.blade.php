{{-- Campos compartidos por el alta y la edición de usuarios --}}
@php($rolesAsignados = old('roles', $usuario->exists ? $usuario->roles->pluck('id_rol')->all() : []))

<div class="row g-3">
    <div class="col-md-6">
        <label for="nombres" class="form-label">Nombres <span class="text-danger">*</span></label>
        <input type="text" id="nombres" name="nombres" value="{{ old('nombres', $usuario->nombres) }}" maxlength="100" required
               @class(['form-control', 'is-invalid' => $errors->has('nombres')])>
        @error('nombres') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="apellidos" class="form-label">Apellidos <span class="text-danger">*</span></label>
        <input type="text" id="apellidos" name="apellidos" value="{{ old('apellidos', $usuario->apellidos) }}" maxlength="100" required
               @class(['form-control', 'is-invalid' => $errors->has('apellidos')])>
        @error('apellidos') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="correo" class="form-label">Correo institucional <span class="text-danger">*</span></label>
        <input type="email" id="correo" name="correo" value="{{ old('correo', $usuario->correo) }}" maxlength="150" required
               @class(['form-control', 'is-invalid' => $errors->has('correo')])>
        @error('correo') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="nombre_usuario" class="form-label">Nombre de usuario <span class="text-danger">*</span></label>
        <input type="text" id="nombre_usuario" name="nombre_usuario" value="{{ old('nombre_usuario', $usuario->nombre_usuario) }}" maxlength="50" required
               @class(['form-control', 'font-monospace', 'is-invalid' => $errors->has('nombre_usuario')])
               aria-describedby="ayudaUsuario">
        <div id="ayudaUsuario" class="form-text">Minúsculas, números, punto, guion o guion bajo. Ej.: jperez</div>
        @error('nombre_usuario') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="cargo" class="form-label">Cargo</label>
        <input type="text" id="cargo" name="cargo" value="{{ old('cargo', $usuario->cargo) }}" maxlength="100"
               @class(['form-control', 'is-invalid' => $errors->has('cargo')])>
        @error('cargo') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    @unless ($usuario->exists)
        <div class="col-md-6">
            <label for="contrasena" class="form-label">Contraseña temporal</label>
            <div class="input-group has-validation">
                <input type="password" id="contrasena" name="contrasena" autocomplete="new-password"
                       @class(['form-control', 'is-invalid' => $errors->has('contrasena')]) aria-describedby="ayudaContrasena">
                <button type="button" class="btn btn-outline-secondary" data-alternar-contrasena="#contrasena" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                @error('contrasena') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div id="ayudaContrasena" class="form-text">Déjela vacía para que el sistema genere una. El usuario deberá cambiarla al ingresar.</div>
        </div>
    @endunless

    <div class="col-12">
        <fieldset>
            <legend class="form-label fs-6">Roles <span class="text-danger">*</span></legend>
            <div class="row g-2">
                @foreach ($roles as $rol)
                    <div class="col-md-4">
                        <div class="form-check border rounded p-2 ps-5 h-100 bg-white">
                            <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $rol->id_rol }}" id="rol{{ $rol->id_rol }}"
                                   @checked(in_array($rol->id_rol, array_map('intval', $rolesAsignados)))>
                            <label class="form-check-label" for="rol{{ $rol->id_rol }}">
                                <strong>{{ $rol->nombre }}</strong> @unless ($rol->activo) <span class="badge text-bg-secondary">Inactivo</span> @endunless
                                <small class="d-block text-body-secondary">{{ $rol->descripcion }}</small>
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
            @error('roles') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            @error('roles.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </fieldset>
    </div>
</div>
