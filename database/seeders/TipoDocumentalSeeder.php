<?php

namespace Database\Seeders;

use App\Models\TipoDocumental;
use Illuminate\Database\Seeder;

/** Tipo documental de la primera versión: Resolución del HCU (4.4.7). */
class TipoDocumentalSeeder extends Seeder
{
    public function run(): void
    {
        TipoDocumental::updateOrCreate(
            ['codigo' => TipoDocumental::RES_HCU],
            [
                'nombre' => 'Resolución del HCU',
                'descripcion' => 'Resoluciones emitidas por el Honorable Consejo Universitario.',
                'campos_adicionales' => null,
                'activo' => true,
            ],
        );
    }
}
