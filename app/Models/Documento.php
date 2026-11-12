<?php

namespace App\Models;

use Database\Factories\DocumentoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Documento oficial (4.4.8). Contiene los datos comunes a todos los tipos;
 * los propios de cada tipo se guardan en "metadatos". El PDF está en Version_Documento.
 */
class Documento extends Model
{
    /** @use HasFactory<DocumentoFactory> */
    use HasFactory;

    /** Expresión indexada (documento_busqueda_idx) para la búsqueda por palabras clave. */
    public const EXPRESION_BUSQUEDA = "to_tsvector('spanish', coalesce(numero, '') || ' ' || coalesce(titulo, '') || ' ' || coalesce(descripcion, ''))";

    protected $table = 'documento';

    protected $primaryKey = 'id_documento';

    const CREATED_AT = 'fecha_registro';

    const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'id_tipo_documental',
        'numero',
        'gestion',
        'fecha_emision',
        'titulo',
        'descripcion',
        'instancia_emisora',
        'metadatos',
        'id_estado_actual',
        'id_usuario_registro',
        'fecha_publicacion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'gestion' => 'integer',
            'fecha_emision' => 'date',
            'metadatos' => 'array',
            'fecha_registro' => 'datetime',
            'fecha_actualizacion' => 'datetime',
            'fecha_publicacion' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    // ----------------------------------------------------------------- Relaciones

    public function tipoDocumental(): BelongsTo
    {
        return $this->belongsTo(TipoDocumental::class, 'id_tipo_documental', 'id_tipo_documental');
    }

    public function estadoActual(): BelongsTo
    {
        return $this->belongsTo(EstadoDocumento::class, 'id_estado_actual', 'id_estado');
    }

    public function usuarioRegistro(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_registro', 'id_usuario');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(VersionDocumento::class, 'id_documento', 'id_documento')->orderBy('numero_version');
    }

    public function versionVigente(): HasOne
    {
        return $this->hasOne(VersionDocumento::class, 'id_documento', 'id_documento')->where('es_vigente', true);
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(SeguimientoDocumento::class, 'id_documento', 'id_documento')->orderBy('fecha')->orderBy('id_seguimiento');
    }

    /** Las observaciones se obtienen a través de sus versiones (Observacion no guarda id_documento). */
    public function observaciones(): HasManyThrough
    {
        return $this->hasManyThrough(
            Observacion::class,
            VersionDocumento::class,
            'id_documento',
            'id_version',
            'id_documento',
            'id_version',
        );
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class, 'id_documento', 'id_documento');
    }

    // ----------------------------------------------------------------- Reglas

    /** Estados en los que el Encargado puede modificar datos o cargar nuevas versiones. */
    public const ESTADOS_EDITABLES = [EstadoDocumento::BORRADOR, EstadoDocumento::OBSERVADO];

    public function esEditable(): bool
    {
        return $this->activo && in_array($this->estadoActual?->codigo, self::ESTADOS_EDITABLES, true);
    }

    /** Ej.: "Resolución del HCU N.º 025/2026". */
    public function nombreCorto(): string
    {
        return trim(($this->tipoDocumental?->nombre ?? 'Documento').' N.º '.$this->numero);
    }

    // ----------------------------------------------------------------- Consultas

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('documento.activo', true);
    }

    /** Documentos visibles en el portal: activos y en un estado público. */
    public function scopePublicados(Builder $query): Builder
    {
        return $query->activos()->whereHas('estadoActual', fn (Builder $q) => $q->where('es_publico', true));
    }

    public function scopeEnEstado(Builder $query, string $codigoEstado): Builder
    {
        return $query->whereHas('estadoActual', fn (Builder $q) => $q->where('codigo', $codigoEstado));
    }

    /** Búsqueda por palabras clave en número, título y descripción (usa el índice GIN). */
    public function scopeBuscar(Builder $query, ?string $texto): Builder
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return $query;
        }

        return $query->whereRaw(self::EXPRESION_BUSQUEDA." @@ websearch_to_tsquery('spanish', ?)", [$texto]);
    }
}
