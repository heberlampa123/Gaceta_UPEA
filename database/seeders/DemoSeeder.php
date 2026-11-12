<?php

namespace Database\Seeders;

use App\Models\Documento;
use App\Models\EstadoDocumento;
use App\Models\Rol;
use App\Models\SeguimientoDocumento;
use App\Models\Usuario;
use App\Services\GestionDocumental;
use App\Support\PdfSimple;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Datos de demostración SOLO para desarrollo:
 *   php artisan db:seed --class=DemoSeeder
 *
 * Crea un Encargado y un Revisor (contraseña: "password") y 20 resoluciones con su PDF,
 * registradas mediante el mismo servicio que usa el sistema (GestionDocumental).
 */
class DemoSeeder extends Seeder
{
    /** Estado final de demostración => acción de seguimiento con la que se llega a él. */
    private const ACCION_POR_ESTADO = [
        EstadoDocumento::PENDIENTE_REVISION => SeguimientoDocumento::ACCION_ENVIO_REVISION,
        EstadoDocumento::OBSERVADO => SeguimientoDocumento::ACCION_DEVOLUCION,
        EstadoDocumento::APROBADO => SeguimientoDocumento::ACCION_APROBACION,
        EstadoDocumento::PUBLICADO => SeguimientoDocumento::ACCION_PUBLICACION,
    ];

    public function run(GestionDocumental $gestion): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder no puede ejecutarse en producción.');

            return;
        }

        $encargado = Usuario::firstWhere('nombre_usuario', 'encargado')
            ?? Usuario::factory()->conRol(Rol::ENCARGADO)->create([
                'nombres' => 'Encargado',
                'apellidos' => 'de Documentos',
                'correo' => 'encargado@upea.edu.bo',
                'nombre_usuario' => 'encargado',
                'cargo' => 'Encargado de documentos',
            ]);

        $revisor = Usuario::firstWhere('nombre_usuario', 'revisor')
            ?? Usuario::factory()->conRol(Rol::REVISOR)->create([
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
            for ($i = 0; $i < $cantidad; $i++) {
                $datos = Documento::factory()->make(['id_usuario_registro' => $encargado->id_usuario])->only([
                    'id_tipo_documental', 'numero', 'gestion', 'fecha_emision', 'titulo', 'descripcion', 'instancia_emisora',
                ]);
                $datos['fecha_emision'] = $datos['fecha_emision']->format('Y-m-d');

                $documento = $gestion->registrar($datos, $this->pdfTemporal($datos), $encargado);

                if ($estado !== EstadoDocumento::BORRADOR) {
                    $this->asignarEstado($documento, $estado, $estado === EstadoDocumento::PENDIENTE_REVISION ? $encargado : $revisor);
                }
            }
        }
    }

    private function pdfTemporal(array $datos): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'demo').'.pdf';

        file_put_contents($ruta, PdfSimple::generar(
            "Resolución del HCU N.º {$datos['numero']}",
            [$datos['titulo'], '', $datos['descripcion'], '', 'Documento de demostración — Gaceta Universitaria UPEA.'],
        ));

        return new UploadedFile($ruta, 'resolucion_'.str_replace('/', '-', $datos['numero']).'.pdf', 'application/pdf', null, true);
    }

    /** Lleva el documento directamente al estado de demostración (el flujo real se implementa en la Fase 5). */
    private function asignarEstado(Documento $documento, string $estado, Usuario $usuario): void
    {
        $anterior = $documento->id_estado_actual;
        $nuevo = EstadoDocumento::idPorCodigo($estado);

        $documento->forceFill([
            'id_estado_actual' => $nuevo,
            'fecha_publicacion' => $estado === EstadoDocumento::PUBLICADO ? now() : null,
        ])->save();

        SeguimientoDocumento::create([
            'id_documento' => $documento->id_documento,
            'id_estado_anterior' => $anterior,
            'id_estado_nuevo' => $nuevo,
            'accion' => self::ACCION_POR_ESTADO[$estado],
            'comentario' => 'Estado asignado para la demostración.',
            'id_usuario' => $usuario->id_usuario,
        ]);
    }
}
