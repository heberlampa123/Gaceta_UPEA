<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Acción que puede autorizarse dentro del sistema (4.4.5). */
class Permiso extends Model
{
    protected $table = 'permiso';

    protected $primaryKey = 'id_permiso';

    public $timestamps = false;

    protected $fillable = ['codigo', 'modulo', 'descripcion'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_permiso', 'id_permiso', 'id_rol');
    }
}
