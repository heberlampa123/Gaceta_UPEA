<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Estado del flujo documental (4.4.10). El sistema lo identifica por su código,
 * de modo que el nombre visible puede cambiar sin afectar el funcionamiento.
 */
class EstadoDocumento extends Model
{
    public const BORRADOR = 'BORRADOR';

    public const PENDIENTE_REVISION = 'PENDIENTE_REVISION';

    public const OBSERVADO = 'OBSERVADO';

    public const APROBADO = 'APROBADO';

    public const PUBLICADO = 'PUBLICADO';

    protected $table = 'estado_documento';

    protected $primaryKey = 'id_estado';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'orden', 'es_publico', 'activo'];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'es_publico' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public static function idPorCodigo(string $codigo): int
    {
        return static::where('codigo', $codigo)->valueOrFail('id_estado');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'id_estado_actual', 'id_estado');
    }
}
