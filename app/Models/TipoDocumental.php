<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

/**
 * Clasificación de los documentos oficiales (4.4.7).
 *
 * "campos_adicionales" define los datos propios de cada tipo, sin modificar la base de datos:
 *   [{nombre: "numero_sesion", etiqueta: "N.º de sesión", tipo: "numero", obligatorio: true, opciones: []}, ...]
 * Los valores de cada documento se guardan en documento.metadatos: {"numero_sesion": 12, ...}
 */
class TipoDocumental extends Model
{
    public const RES_HCU = 'RES_HCU';

    /** Tipos de dato disponibles para los campos adicionales. */
    public const TIPOS_CAMPO = [
        'texto' => 'Texto corto',
        'texto_largo' => 'Texto largo',
        'numero' => 'Número',
        'fecha' => 'Fecha',
        'seleccion' => 'Lista de opciones',
        'si_no' => 'Sí / No',
    ];

    protected $table = 'tipo_documental';

    protected $primaryKey = 'id_tipo_documental';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'campos_adicionales', 'activo'];

    protected function casts(): array
    {
        return [
            'campos_adicionales' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'id_tipo_documental', 'id_tipo_documental');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    // ----------------------------------------------------------------- Campos adicionales

    /** @return array<int, array{nombre: string, etiqueta: string, tipo: string, obligatorio: bool, opciones: string[]}> */
    public function campos(): array
    {
        return $this->campos_adicionales ?? [];
    }

    /** Reglas de validación de documento.metadatos según los campos del tipo. */
    public function reglasMetadatos(): array
    {
        $reglas = [];

        foreach ($this->campos() as $campo) {
            $base = ($campo['obligatorio'] ?? false) && $campo['tipo'] !== 'si_no' ? ['required'] : ['nullable'];

            $reglas['metadatos.'.$campo['nombre']] = [...$base, ...match ($campo['tipo']) {
                'texto' => ['string', 'max:255'],
                'texto_largo' => ['string', 'max:5000'],
                'numero' => ['numeric', 'between:-999999999,999999999'],
                'fecha' => ['date'],
                'seleccion' => ['string', Rule::in($campo['opciones'] ?? [])],
                'si_no' => ['boolean'],
                default => ['string'],
            }];
        }

        return $reglas;
    }

    /** Nombres legibles para los mensajes de validación: "metadatos.numero_sesion" => "N.º de sesión". */
    public function atributosMetadatos(): array
    {
        return collect($this->campos())
            ->mapWithKeys(fn ($campo) => ['metadatos.'.$campo['nombre'] => mb_strtolower($campo['etiqueta'])])
            ->all();
    }

    /** Conserva solo los campos definidos en el tipo y convierte cada valor a su tipo de dato. */
    public function normalizarMetadatos(?array $valores): ?array
    {
        if ($this->campos() === []) {
            return null;
        }

        $resultado = [];

        foreach ($this->campos() as $campo) {
            $valor = $valores[$campo['nombre']] ?? null;

            $resultado[$campo['nombre']] = match (true) {
                $campo['tipo'] === 'si_no' => filter_var($valor, FILTER_VALIDATE_BOOLEAN),
                $valor === null || $valor === '' => null,
                $campo['tipo'] === 'numero' => $valor + 0,
                $campo['tipo'] === 'fecha' => Carbon::parse($valor)->format('Y-m-d'),
                default => trim((string) $valor),
            };
        }

        return $resultado;
    }

    /** Valor de un metadato listo para mostrarse en pantalla. */
    public static function formatearValor(array $campo, mixed $valor): string
    {
        if ($campo['tipo'] === 'si_no') {
            return $valor ? 'Sí' : 'No';
        }

        if ($valor === null || $valor === '') {
            return '—';
        }

        return match ($campo['tipo']) {
            'fecha' => Carbon::parse($valor)->format('d/m/Y'),
            'numero' => rtrim(rtrim(number_format((float) $valor, 2, ',', '.'), '0'), ','),
            default => (string) $valor,
        };
    }
}
