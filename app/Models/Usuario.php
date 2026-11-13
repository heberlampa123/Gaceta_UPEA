<?php

namespace App\Models;

use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Cuenta con acceso al panel administrativo (4.4.3).
 * Las cuentas no se eliminan: se desactivan con "activo = false".
 */
class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasApiTokens, HasFactory;

    public const MAX_INTENTOS_FALLIDOS = 5;

    public const MINUTOS_BLOQUEO = 15;

    protected $table = 'usuario';

    protected $primaryKey = 'id_usuario';

    const CREATED_AT = 'fecha_creacion';

    const UPDATED_AT = null;

    /** El modelo no usa "recordarme" (no existe la columna remember_token). */
    protected $rememberTokenName = '';

    protected $fillable = [
        'nombres',
        'apellidos',
        'correo',
        'nombre_usuario',
        'contrasena_hash',
        'cargo',
        'activo',
        'debe_cambiar_contrasena',
    ];

    protected $hidden = [
        'contrasena_hash',
    ];

    /** Permisos ya consultados durante la petición actual. */
    protected ?array $codigosPermiso = null;

    protected function casts(): array
    {
        return [
            'contrasena_hash' => 'hashed',
            'activo' => 'boolean',
            'debe_cambiar_contrasena' => 'boolean',
            'intentos_fallidos' => 'integer',
            'bloqueado_hasta' => 'datetime',
            'ultimo_acceso' => 'datetime',
            'fecha_creacion' => 'datetime',
        ];
    }

    /** Laravel valida la contraseña contra esta columna. */
    public function getAuthPasswordName(): string
    {
        return 'contrasena_hash';
    }

    // ----------------------------------------------------------------- Relaciones

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'usuario_rol', 'id_usuario', 'id_rol');
    }

    public function documentosRegistrados(): HasMany
    {
        return $this->hasMany(Documento::class, 'id_usuario_registro', 'id_usuario');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class, 'id_usuario_destino', 'id_usuario');
    }

    // ----------------------------------------------------------------- Permisos

    /** Códigos de permiso del usuario, obtenidos de sus roles activos. */
    public function codigosPermiso(): array
    {
        return $this->codigosPermiso ??= Permiso::query()
            ->whereHas('roles', fn (Builder $q) => $q
                ->where('rol.activo', true)
                ->whereHas('usuarios', fn (Builder $u) => $u->where('usuario.id_usuario', $this->id_usuario)))
            ->pluck('codigo')
            ->all();
    }

    /** El sistema verifica permisos por su código, nunca por el nombre del rol. */
    public function tienePermiso(string $codigo): bool
    {
        return $this->activo && in_array($codigo, $this->codigosPermiso(), true);
    }

    public function tieneRol(string $codigoRol): bool
    {
        return $this->roles->contains('codigo', $codigoRol);
    }

    public function olvidarPermisos(): void
    {
        $this->codigosPermiso = null;
    }

    // ----------------------------------------------------------------- Estado de la cuenta

    public function estaBloqueado(): bool
    {
        return $this->bloqueado_hasta !== null && $this->bloqueado_hasta->isFuture();
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /** Usuarios activos que tienen un permiso a través de alguno de sus roles activos. */
    public function scopeConPermiso(Builder $query, string $codigo): Builder
    {
        return $query->activos()->whereHas('roles', fn (Builder $r) => $r
            ->where('rol.activo', true)
            ->whereHas('permisos', fn (Builder $p) => $p->where('codigo', $codigo)));
    }

    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->nombres} {$this->apellidos}"));
    }
}
