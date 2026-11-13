<?php

namespace Database\Seeders;

use App\Models\TipoRelacionDocumental;
use Illuminate\Database\Seeder;

/**
 * Tipos de relación entre normas. Para agregar uno nuevo (ej.: «Complementa», «Reglamenta»),
 * añadir una línea y ejecutar: php artisan db:seed --class=TipoRelacionDocumentalSeeder
 * Para retirar uno sin perder el historial, poner activo = false (deja de ofrecerse en el formulario).
 */
class TipoRelacionDocumentalSeeder extends Seeder
{
    /** codigo => [nombre, nombre_inverso, descripcion, quita_vigencia, requiere_detalle, orden, activo] */
    public const TIPOS = [
        TipoRelacionDocumental::ABROGA => ['Abroga', 'Abrogada por',
            'Deja sin efecto la norma completa. La norma abrogada deja de ser vigente y ya no se muestra en el portal público.',
            true, false, 1, true],
        TipoRelacionDocumental::DEROGA => ['Deroga parcialmente', 'Derogada parcialmente por',
            'Deja sin efecto solo una parte de la norma (indique cuál). La norma sigue vigente en lo demás.',
            false, true, 2, true],
        TipoRelacionDocumental::MODIFICA => ['Modifica', 'Modificada por',
            'Cambia parte del contenido de la norma (indique qué). La norma sigue vigente con las modificaciones.',
            false, true, 3, true],
    ];

    public function run(): void
    {
        foreach (self::TIPOS as $codigo => [$nombre, $inverso, $descripcion, $quitaVigencia, $requiereDetalle, $orden, $activo]) {
            TipoRelacionDocumental::updateOrCreate(['codigo' => $codigo], [
                'nombre' => $nombre,
                'nombre_inverso' => $inverso,
                'descripcion' => $descripcion,
                'quita_vigencia' => $quitaVigencia,
                'requiere_detalle' => $requiereDetalle,
                'orden' => $orden,
                'activo' => $activo,
            ]);
        }
    }
}
