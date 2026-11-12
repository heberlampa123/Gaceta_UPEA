<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Cuenta del administrador inicial (datos en config/gaceta.php y .env).
 * Si ya existe, no se modifica su contraseña.
 */
class AdministradorSeeder extends Seeder
{
    public function run(): void
    {
        $datos = config('gaceta.admin');

        if (Usuario::where('nombre_usuario', $datos['nombre_usuario'])->exists()) {
            return;
        }

        $contrasena = $datos['contrasena'] ?: Str::password(12, symbols: false);

        $admin = Usuario::create([
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'correo' => $datos['correo'],
            'nombre_usuario' => $datos['nombre_usuario'],
            'contrasena_hash' => $contrasena,
            'cargo' => 'Administrador del sistema',
            'activo' => true,
            'debe_cambiar_contrasena' => true,
        ]);

        $admin->roles()->sync([Rol::where('codigo', Rol::ADMIN)->valueOrFail('id_rol')]);

        if (! $datos['contrasena']) {
            $this->command?->warn("ADMIN_CONTRASENA no está definida. Contraseña temporal generada: {$contrasena}");
        }
    }
}
