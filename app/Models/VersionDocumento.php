<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versión de un archivo PDF (4.4.9). Cada documento tiene como máximo una versión vigente;
 * las anteriores se conservan.
 */
class VersionDocumento extends Model
{
    protected $table = 'version_documento';

    protected $primaryKey = 'id_version';

    const CREATED_AT = 'fecha_carga';

    const UPDATED_AT = null;

    protected $fillable = [
        'id_documento',
        'numero_version',
        'nombre_original',
        'ruta_archivo',
        'tamano_bytes',
        'hash_sha256',
        'motivo_cambio',
        'es_vigente',
        'id_usuario_carga',
    ];

    protected function casts(): array
    {
        return [
            'numero_version' => 'integer',
            'tamano_bytes' => 'integer',
            'es_vigente' => 'boolean',
            'fecha_carga' => 'datetime',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'id_documento', 'id_documento');
    }

    public function usuarioCarga(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_carga', 'id_usuario');
    }

    public function observaciones(): HasMany
    {
        return $this->hasMany(Observacion::class, 'id_version', 'id_version');
    }
}
