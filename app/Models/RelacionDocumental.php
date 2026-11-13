<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "El documento ORIGEN (nuevo) <tipo> al documento DESTINO (anterior)".
 * Ej.: Res. 045/2026 (origen) ABROGA a Res. 025/2026 (destino).
 *
 * La relación surte efecto solo cuando el documento origen está publicado y activo:
 * mientras está en borrador o revisión, es un anuncio pendiente que solo se ve en el panel.
 */
class RelacionDocumental extends Model
{
    protected $table = 'relacion_documental';

    protected $primaryKey = 'id_relacion';

    const CREATED_AT = 'fecha_registro';

    const UPDATED_AT = null;

    protected $fillable = ['id_documento_origen', 'id_documento_destino', 'id_tipo_relacion', 'detalle', 'id_usuario_registro'];

    protected function casts(): array
    {
        return ['fecha_registro' => 'datetime'];
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'id_documento_origen', 'id_documento');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'id_documento_destino', 'id_documento');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoRelacionDocumental::class, 'id_tipo_relacion', 'id_tipo_relacion');
    }

    public function usuarioRegistro(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_registro', 'id_usuario');
    }

    /** Relaciones en vigor: su documento origen ya está publicado y activo. */
    public function scopeEfectivas(Builder $query): Builder
    {
        return $query->whereHas('origen', fn (Builder $q) => $q->publicados());
    }

    /** Relaciones cuyo tipo deja sin vigencia a la norma afectada (Abroga). */
    public function scopeQueQuitanVigencia(Builder $query): Builder
    {
        return $query->whereHas('tipo', fn (Builder $q) => $q->where('quita_vigencia', true));
    }

    public function esEfectiva(): bool
    {
        return $this->origen?->activo && $this->origen?->estadoActual?->es_publico;
    }
}
