<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento en el recorrido de un documento (4.4.11).
 * Solo inserciones: la base de datos rechaza UPDATE y DELETE.
 */
class SeguimientoDocumento extends Model
{
    public const ACCION_REGISTRO = 'REGISTRO';

    public const ACCION_ENVIO_REVISION = 'ENVIO_REVISION';

    public const ACCION_DEVOLUCION = 'DEVOLUCION';

    public const ACCION_APROBACION = 'APROBACION';

    public const ACCION_PUBLICACION = 'PUBLICACION';

    public const ACCION_NUEVA_VERSION = 'NUEVA_VERSION';

    public const ACCION_ANULACION = 'ANULACION';

    /** Otra norma publicada abrogó, derogó o modificó este documento (relaciones entre normas). */
    public const ACCION_AFECTACION = 'AFECTACION';

    protected $table = 'seguimiento_documento';

    protected $primaryKey = 'id_seguimiento';

    const CREATED_AT = 'fecha';

    const UPDATED_AT = null;

    protected $fillable = [
        'id_documento',
        'id_estado_anterior',
        'id_estado_nuevo',
        'id_version',
        'accion',
        'comentario',
        'id_usuario',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'datetime'];
    }

    /** Nombres legibles de las acciones, para el historial del documento (2.4). */
    public const ETIQUETAS = [
        self::ACCION_REGISTRO => 'Registro del documento',
        self::ACCION_ENVIO_REVISION => 'Envío a revisión',
        self::ACCION_DEVOLUCION => 'Devolución con observaciones',
        self::ACCION_APROBACION => 'Aprobación para publicación',
        self::ACCION_PUBLICACION => 'Publicación',
        self::ACCION_NUEVA_VERSION => 'Nueva versión del PDF',
        self::ACCION_ANULACION => 'Anulación del borrador',
        self::ACCION_AFECTACION => 'Afectada por otra norma',
    ];

    public function etiqueta(): string
    {
        return self::ETIQUETAS[$this->accion] ?? ucfirst(mb_strtolower(str_replace('_', ' ', $this->accion)));
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'id_documento', 'id_documento');
    }

    public function estadoAnterior(): BelongsTo
    {
        return $this->belongsTo(EstadoDocumento::class, 'id_estado_anterior', 'id_estado');
    }

    public function estadoNuevo(): BelongsTo
    {
        return $this->belongsTo(EstadoDocumento::class, 'id_estado_nuevo', 'id_estado');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(VersionDocumento::class, 'id_version', 'id_version');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
