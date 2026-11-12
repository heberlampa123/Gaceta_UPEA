<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Observación del Revisor sobre una versión concreta (4.4.12).
 * Pendiente mientras fecha_atencion sea NULL.
 */
class Observacion extends Model
{
    protected $table = 'observacion';

    protected $primaryKey = 'id_observacion';

    const CREATED_AT = 'fecha_registro';

    const UPDATED_AT = null;

    protected $fillable = [
        'id_version',
        'id_usuario_revisor',
        'descripcion',
        'respuesta',
        'id_usuario_atiende',
        'fecha_atencion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_registro' => 'datetime',
            'fecha_atencion' => 'datetime',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(VersionDocumento::class, 'id_version', 'id_version');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_revisor', 'id_usuario');
    }

    public function usuarioAtiende(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_atiende', 'id_usuario');
    }

    public function estaAtendida(): bool
    {
        return $this->fecha_atencion !== null;
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->whereNull('fecha_atencion');
    }
}
