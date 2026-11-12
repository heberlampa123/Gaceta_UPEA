<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de una operación importante realizada en el sistema (4.4.14).
 * Solo inserciones: la base de datos rechaza UPDATE y DELETE.
 */
class Auditoria extends Model
{
    public const INICIO_SESION = 'INICIO_SESION';

    public const INICIO_SESION_FALLIDO = 'INICIO_SESION_FALLIDO';

    public const CIERRE_SESION = 'CIERRE_SESION';

    public const CREAR = 'CREAR';

    public const MODIFICAR = 'MODIFICAR';

    public const DESACTIVAR = 'DESACTIVAR';

    public const PUBLICAR = 'PUBLICAR';

    protected $table = 'auditoria';

    protected $primaryKey = 'id_auditoria';

    const CREATED_AT = 'fecha';

    const UPDATED_AT = null;

    protected $fillable = [
        'id_usuario',
        'accion',
        'entidad',
        'id_registro',
        'detalle',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'detalle' => 'array',
            'fecha' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
