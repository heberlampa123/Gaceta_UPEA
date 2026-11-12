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

    public const ACTIVAR = 'ACTIVAR';

    public const PUBLICAR = 'PUBLICAR';

    public const BLOQUEO_CUENTA = 'BLOQUEO_CUENTA';

    public const DESBLOQUEO_CUENTA = 'DESBLOQUEO_CUENTA';

    public const RESTABLECER_CONTRASENA = 'RESTABLECER_CONTRASENA';

    public const CAMBIO_CONTRASENA = 'CAMBIO_CONTRASENA';

    public const CAMBIO_PERMISOS = 'CAMBIO_PERMISOS';

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

    /** Nombres legibles de las acciones, para mostrarlos en pantalla. */
    public const ETIQUETAS = [
        self::INICIO_SESION => 'Inicio de sesión',
        self::INICIO_SESION_FALLIDO => 'Intento fallido de inicio de sesión',
        self::CIERRE_SESION => 'Cierre de sesión',
        self::CREAR => 'Creación',
        self::MODIFICAR => 'Modificación',
        self::DESACTIVAR => 'Desactivación',
        self::ACTIVAR => 'Activación',
        self::PUBLICAR => 'Publicación',
        self::BLOQUEO_CUENTA => 'Bloqueo de cuenta',
        self::DESBLOQUEO_CUENTA => 'Desbloqueo de cuenta',
        self::RESTABLECER_CONTRASENA => 'Restablecimiento de contraseña',
        self::CAMBIO_CONTRASENA => 'Cambio de contraseña',
        self::CAMBIO_PERMISOS => 'Cambio de permisos',
    ];

    public function etiqueta(): string
    {
        return self::ETIQUETAS[$this->accion] ?? ucfirst(mb_strtolower(str_replace('_', ' ', $this->accion)));
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
