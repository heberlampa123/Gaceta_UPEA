<?php

namespace App\Http\Requests;

use App\Models\TipoDocumental;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validación del alta y la edición de tipos documentales y de sus campos adicionales (RF-05).
 * El código del tipo no cambia después de creado.
 */
class GuardarTipoDocumentalRequest extends FormRequest
{
    public const MAX_CAMPOS = 20;

    public function authorize(): bool
    {
        return $this->user()->can('tipo_documental.gestionar');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('codigo')) {
            $this->merge(['codigo' => mb_strtoupper(trim((string) $this->input('codigo')))]);
        }

        // Normaliza los campos: reindexa, genera el nombre interno desde la etiqueta y convierte las opciones a lista.
        $campos = collect($this->input('campos', []))
            ->filter(fn ($campo) => is_array($campo) && trim((string) ($campo['etiqueta'] ?? '')) !== '')
            ->map(function (array $campo) {
                $etiqueta = trim((string) $campo['etiqueta']);
                // Mismo criterio que resources/js/campos-adicionales.js: "N.º de sesión" → "no_de_sesion".
                $nombre = trim((string) ($campo['nombre'] ?? ''))
                    ?: preg_replace('/^(\d)/', 'campo_$1', Str::slug(Str::ascii($etiqueta), '_'));

                return [
                    'nombre' => mb_strtolower($nombre),
                    'etiqueta' => $etiqueta,
                    'tipo' => $campo['tipo'] ?? 'texto',
                    'obligatorio' => filter_var($campo['obligatorio'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'opciones' => collect(preg_split('/\r\n|\r|\n/', (string) ($campo['opciones'] ?? '')))
                        ->map(fn ($opcion) => trim($opcion))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $this->merge([
            'activo' => $this->boolean('activo'),
            'campos' => $campos,
        ]);
    }

    public function rules(): array
    {
        /** @var TipoDocumental|null $tipo */
        $tipo = $this->route('tipo');

        return [
            'codigo' => $tipo
                ? ['prohibited']
                : ['required', 'string', 'max:30', 'regex:/^[A-Z][A-Z0-9_]*$/', Rule::unique('tipo_documental', 'codigo')],
            'nombre' => ['required', 'string', 'max:100', Rule::unique('tipo_documental', 'nombre')->ignore($tipo?->id_tipo_documental, 'id_tipo_documental')],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['boolean'],
            'campos' => ['array', 'max:'.self::MAX_CAMPOS],
            'campos.*.nombre' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'campos.*.etiqueta' => ['required', 'string', 'max:100'],
            'campos.*.tipo' => ['required', Rule::in(array_keys(TipoDocumental::TIPOS_CAMPO))],
            'campos.*.obligatorio' => ['boolean'],
            'campos.*.opciones' => ['array', 'max:50'],
            'campos.*.opciones.*' => ['string', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            foreach ($this->input('campos', []) as $i => $campo) {
                if ($campo['tipo'] === 'seleccion' && count($campo['opciones']) < 2) {
                    $validator->errors()->add("campos.{$i}.opciones", "El campo «{$campo['etiqueta']}» es una lista: escriba al menos dos opciones, una por línea.");
                }
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'campos.*.nombre' => 'nombre interno',
            'campos.*.etiqueta' => 'etiqueta',
            'campos.*.tipo' => 'tipo de dato',
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.regex' => 'El código debe estar en mayúsculas, empezar con una letra y usar solo letras, números y guion bajo (ej.: RES_RECTORAL).',
            'codigo.prohibited' => 'El código de un tipo documental no puede modificarse.',
            'campos.*.nombre.regex' => 'El nombre interno «:input» solo puede usar minúsculas, números y guion bajo, y debe empezar con una letra.',
            'campos.*.nombre.distinct' => 'Hay dos campos con el mismo nombre interno «:input».',
            'campos.max' => 'Un tipo documental puede tener como máximo :max campos adicionales.',
        ];
    }

    /** Datos listos para guardar; los campos sin opciones no guardan la lista vacía de forma innecesaria. */
    public function datosTipo(): array
    {
        $datos = $this->validated();

        $datos['campos_adicionales'] = collect($datos['campos'] ?? [])
            ->map(fn ($campo) => $campo['tipo'] === 'seleccion' ? $campo : [...$campo, 'opciones' => []])
            ->values()
            ->all() ?: null;

        unset($datos['campos']);

        return $datos;
    }
}
