<?php

namespace Database\Seeders;

use App\Models\Documento;
use App\Models\EstadoDocumento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\FlujoDocumental;
use App\Services\GestionDocumental;
use App\Support\PdfSimple;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Datos de demostración SOLO para desarrollo:
 *   php artisan db:seed --class=DemoSeeder
 *
 * Crea un Encargado y un Revisor (contraseña: "password") y 20 resoluciones con su PDF.
 * Cada documento pasa por el flujo real (GestionDocumental y FlujoDocumental), de modo que
 * su seguimiento, observaciones y auditoría quedan igual que si se hubieran trabajado en el panel.
 */
class DemoSeeder extends Seeder
{
    public function run(GestionDocumental $gestion, FlujoDocumental $flujo): void
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

                $this->llevarAlEstado($documento, $estado, $gestion, $flujo, $encargado, $revisor, $i);
            }
        }
    }

    /** Recorre el flujo hasta el estado deseado; algunos documentos pasan por una devolución y corrección. */
    private function llevarAlEstado(Documento $documento, string $estado, GestionDocumental $gestion, FlujoDocumental $flujo, Usuario $encargado, Usuario $revisor, int $indice): void
    {
        if ($estado === EstadoDocumento::BORRADOR) {
            return;
        }

        $flujo->enviarARevision($documento, $encargado);

        if ($estado === EstadoDocumento::PENDIENTE_REVISION) {
            return;
        }

        // Los observados, y uno de cada tres de los demás, reciben observaciones.
        if ($estado === EstadoDocumento::OBSERVADO || $indice % 3 === 0) {
            $flujo->devolver($documento, $revisor, [
                'El número de resolución del encabezado no coincide con el registrado.',
                'Falta la firma del Secretario General en la última página.',
            ]);

            if ($estado === EstadoDocumento::OBSERVADO) {
                return;
            }

            $documento->refresh();
            $gestion->agregarVersion($documento, $this->pdfTemporal($documento->only(['numero', 'titulo', 'descripcion']), 'corregida'), 'Corrección del encabezado y firma', $encargado);

            $respuestas = $documento->observaciones()->pendientes()->pluck('observacion.id_observacion')
                ->mapWithKeys(fn ($id) => [$id => 'Se corrigió en la versión 2.'])
                ->all();

            $flujo->reenviar($documento, $encargado, $respuestas);
        }

        $flujo->aprobar($documento, $revisor);

        if ($estado === EstadoDocumento::PUBLICADO) {
            $flujo->publicar($documento, $revisor);
        }
    }

    private function pdfTemporal(array $datos, string $variante = ''): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'demo').'.pdf';

        file_put_contents($ruta, PdfSimple::generar(
            "Resolución del HCU N.º {$datos['numero']}",
            [$datos['titulo'], '', (string) $datos['descripcion'], '', trim("Documento de demostración {$variante} — Gaceta Universitaria UPEA.")],
        ));

        return new UploadedFile($ruta, 'resolucion_'.str_replace('/', '-', $datos['numero']).'.pdf', 'application/pdf', null, true);
    }
}
