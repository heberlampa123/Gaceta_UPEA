<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Datos iniciales obligatorios del sistema (catálogos + administrador).
 * Para datos de demostración: php artisan db:seed --class=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesYPermisosSeeder::class,
            EstadoDocumentoSeeder::class,
            TipoDocumentalSeeder::class,
            AdministradorSeeder::class,
        ]);
    }
}
