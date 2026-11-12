<?php

namespace App\Http\Requests;

use App\Models\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validación del alta y la edición de roles. El código no cambia después de creado. */
class GuardarRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rol.gestionar');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('codigo')) {
            $this->merge(['codigo' => mb_strtoupper(trim((string) $this->input('codigo')))]);
        }

        $this->merge(['activo' => $this->boolean('activo')]);
    }

    public function rules(): array
    {
        /** @var Rol|null $rol */
        $rol = $this->route('rol');

        return [
            'codigo' => $rol
                ? ['prohibited']
                : ['required', 'string', 'max:30', 'regex:/^[A-Z][A-Z0-9_]*$/', Rule::unique('rol', 'codigo')],
            'nombre' => ['required', 'string', 'max:80'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['boolean'],
            'permisos' => ['nullable', 'array'],
            'permisos.*' => ['integer', Rule::exists('permiso', 'id_permiso')],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.regex' => 'El código debe estar en mayúsculas, empezar con una letra y usar solo letras, números y guion bajo (ej.: SECRETARIA).',
            'codigo.prohibited' => 'El código de un rol no puede modificarse.',
        ];
    }
}
