<?php

namespace Database\Seeders;

use App\Models\Documento;
use App\Models\EstadoDocumento;
use App\Models\Rol;
use App\Models\SeguimientoDocumento;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración SOLO para desarrollo:
 *   php artisan db:seed --class=DemoSeeder
 *
 * Crea un Encargado y un Revisor (contraseña: "password") y resoluciones
 * en distintos estados con su registro inicial de seguimiento.
 * Los archivos PDF se incorporan en la Fase 3.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder no puede ejecutarse en producción.');

            return;
        }

        $encargado = Usuario::factory()->conRol(Rol::ENCARGADO)->create([
            'nombres' => 'Encargado',
            'apellidos' => 'de Documentos',
            'correo' => 'encargado@upea.edu.bo',
            'nombre_usuario' => 'encargado',
            'cargo' => 'Encargado de documentos',
        ]);

        Usuario::factory()->conRol(Rol::REVISOR)->create([
            'nombres' => 'Revisor',
            'apellidos' => 'Publicador',
            'correo' => 'revisor@upea.edu.bo',
            'nombre_usuario' => 'revisor',
            'cargo' => 'Revisor / Publicador',
        ]);

        $cantidades = [
            EstadoDocumento::BORRADOR => 3,
            EstadoDocumento::PENDIENTE_REVISION => 3,
            EstadoDocumento::OBSERVADO => 2,
            EstadoDocumento::APROBADO => 2,
            EstadoDocumento::PUBLICADO => 10,
        ];

        foreach ($cantidades as $estado => $cantidad) {
            Documento::factory($cantidad)
                ->enEstado($estado)
                ->create(['id_usuario_registro' => $encargado->id_usuario])
                ->each(fn (Documento $documento) => SeguimientoDocumento::create([
                    'id_documento' => $documento->id_documento,
                    'id_estado_anterior' => null,
                    'id_estado_nuevo' => $documento->id_estado_actual,
                    'accion' => SeguimientoDocumento::ACCION_REGISTRO,
                    'comentario' => 'Registro de datos de demostración.',
                    'id_usuario' => $encargado->id_usuario,
                ]));
        }
    }
}
