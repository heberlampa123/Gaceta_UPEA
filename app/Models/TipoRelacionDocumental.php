<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de relación entre normas (Abroga, Deroga, Modifica…). Es un catálogo: para agregar,
 * renombrar o retirar un tipo basta con editar TipoRelacionDocumentalSeeder (o desactivarlo).
 * El sistema solo se apoya en dos indicadores:
 *   - quita_vigencia: la norma afectada deja de ser vigente y desaparece del portal público.
 *   - requiere_detalle: hay que indicar qué parte se afecta (ej.: "Artículos 3 y 5").
 */
class TipoRelacionDocumental extends Model
{
    public const ABROGA = 'ABROGA';

    public const DEROGA = 'DEROGA';

    public const MODIFICA = 'MODIFICA';

    protected $table = 'tipo_relacion_documental';

    protected $primaryKey = 'id_tipo_relacion';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'nombre_inverso', 'descripcion', 'quita_vigencia', 'requiere_detalle', 'orden', 'activo'];

    protected function casts(): array
    {
        return [
            'quita_vigencia' => 'boolean',
            'requiere_detalle' => 'boolean',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->orderBy('orden');
    }
}
