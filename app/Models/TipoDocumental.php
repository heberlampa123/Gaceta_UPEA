<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Clasificación de los documentos oficiales (4.4.7). */
class TipoDocumental extends Model
{
    public const RES_HCU = 'RES_HCU';

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
}
