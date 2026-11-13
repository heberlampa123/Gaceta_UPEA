<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aviso interno dirigido a un usuario (4.4.13). No leída mientras fecha_lectura sea NULL. */
class Notificacion extends Model
{
    public const NUEVO_PENDIENTE = 'NUEVO_PENDIENTE';

    public const OBSERVADO = 'OBSERVADO';

    public const APROBADO = 'APROBADO';

    public const PUBLICADO = 'PUBLICADO';

    protected $table = 'notificacion';

    protected $primaryKey = 'id_notificacion';

    const CREATED_AT = 'fecha_creacion';

    const UPDATED_AT = null;

    protected $fillable = [
        'id_usuario_destino',
        'id_documento',
        'tipo',
        'titulo',
        'mensaje',
        'fecha_lectura',
    ];

    protected function casts(): array
    {
        return [
            'fecha_creacion' => 'datetime',
            'fecha_lectura' => 'datetime',
        ];
    }

    public function usuarioDestino(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_destino', 'id_usuario');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'id_documento', 'id_documento');
    }

    public function scopeNoLeidas(Builder $query): Builder
    {
        return $query->whereNull('fecha_lectura');
    }

    /** Ícono de Bootstrap Icons según el tipo de aviso. */
    public function icono(): string
    {
        return match ($this->tipo) {
            self::NUEVO_PENDIENTE => 'inbox',
            self::OBSERVADO => 'exclamation-circle',
            self::APROBADO => 'check2-circle',
            self::PUBLICADO => 'globe',
            default => 'bell',
        };
    }

    public function marcarComoLeida(): void
    {
        if ($this->fecha_lectura === null) {
            $this->forceFill(['fecha_lectura' => now()])->save();
        }
    }
}
