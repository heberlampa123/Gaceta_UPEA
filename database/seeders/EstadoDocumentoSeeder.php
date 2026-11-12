<?php

namespace Database\Seeders;

use App\Models\EstadoDocumento;
use Illuminate\Database\Seeder;

/** Estados iniciales del flujo documental (4.4.10). */
class EstadoDocumentoSeeder extends Seeder
{
    /** codigo => [nombre, descripcion, orden, es_publico] */
    public const ESTADOS = [
        EstadoDocumento::BORRADOR => ['Borrador', 'El documento ha sido registrado, pero todavía no ha sido enviado a revisión.', 1, false],
        EstadoDocumento::PENDIENTE_REVISION => ['Pendiente de revisión', 'El documento ha sido enviado y espera ser revisado.', 2, false],
        EstadoDocumento::OBSERVADO => ['Observado', 'El documento presenta observaciones y debe ser corregido.', 3, false],
        EstadoDocumento::APROBADO => ['Aprobado para publicación', 'El documento ha sido autorizado para su publicación en la Gaceta.', 4, false],
        EstadoDocumento::PUBLICADO => ['Publicado', 'El documento se encuentra disponible para consulta pública.', 5, true],
    ];

    public function run(): void
    {
        foreach (self::ESTADOS as $codigo => [$nombre, $descripcion, $orden, $esPublico]) {
            EstadoDocumento::updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'descripcion' => $descripcion, 'orden' => $orden, 'es_publico' => $esPublico, 'activo' => true],
            );
        }
    }
}
