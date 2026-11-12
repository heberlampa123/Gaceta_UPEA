<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Validación del alta y la edición de cuentas (solo el administrador). */
class GuardarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('usuario.gestionar');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'correo' => mb_strtolower(trim((string) $this->input('correo'))),
            'nombre_usuario' => mb_strtolower(trim((string) $this->input('nombre_usuario'))),
        ]);
    }

    public function rules(): array
    {
        /** @var Usuario|null $usuario */
        $usuario = $this->route('usuario');
        $idIgnorado = $usuario?->id_usuario;

        return [
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:150', Rule::unique('usuario', 'correo')->ignore($idIgnorado, 'id_usuario')],
            'nombre_usuario' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('usuario', 'nombre_usuario')->ignore($idIgnorado, 'id_usuario')],
            'cargo' => ['nullable', 'string', 'max:100'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::exists('rol', 'id_rol')->where('activo', true)],
            // Solo en el alta: si se deja vacía, el sistema genera una contraseña temporal.
            'contrasena' => $usuario ? ['prohibited'] : ['nullable', 'string', Password::defaults()],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre_usuario' => 'nombre de usuario',
            'contrasena' => 'contraseña temporal',
            'roles' => 'roles',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_usuario.regex' => 'El nombre de usuario solo puede contener letras minúsculas, números, punto, guion y guion bajo.',
            'roles.required' => 'Debe asignar al menos un rol.',
        ];
    }
}
