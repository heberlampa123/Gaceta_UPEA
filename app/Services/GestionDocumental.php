<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\EstadoDocumento;
use App\Models\SeguimientoDocumento;
use App\Models\Usuario;
use App\Models\VersionDocumento;
use App\Support\Auditor;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Gestión documental (RF-02) y control de versiones de los PDF (RF-04).
 *
 * Reglas:
 *  - Todo documento se registra con su PDF (versión 1) en estado Borrador.
 *  - Una nueva versión deja de vigente a la anterior en la misma transacción; nada se elimina.
 *  - Cada archivo guarda su tamaño y su huella SHA-256 para verificar que no fue alterado.
 *  - El registro y cada nueva versión quedan en el seguimiento del documento y en la auditoría.
 */
class GestionDocumental
{
    public function registrar(array $datos, UploadedFile $pdf, Usuario $usuario): Documento
    {
        $archivo = $this->guardarArchivo($pdf, (int) $datos['gestion']);

        try {
            return DB::transaction(function () use ($datos, $archivo, $usuario) {
                $documento = Documento::create([
                    'id_tipo_documental' => $datos['id_tipo_documental'],
                    'numero' => $datos['numero'],
                    'gestion' => $datos['gestion'],
                    'fecha_emision' => $datos['fecha_emision'],
                    'titulo' => $datos['titulo'],
                    'descripcion' => $datos['descripcion'] ?? null,
                    'instancia_emisora' => $datos['instancia_emisora'] ?? null,
                    'metadatos' => $datos['metadatos'] ?? null,
                    'id_estado_actual' => EstadoDocumento::idPorCodigo(EstadoDocumento::BORRADOR),
                    'id_usuario_registro' => $usuario->id_usuario,
                    'activo' => true,
                ]);

                $version = $this->crearVersion($documento, $archivo, 1, 'Versión inicial', $usuario);

                SeguimientoDocumento::create([
                    'id_documento' => $documento->id_documento,
                    'id_estado_anterior' => null,
                    'id_estado_nuevo' => $documento->id_estado_actual,
                    'id_version' => $version->id_version,
                    'accion' => SeguimientoDocumento::ACCION_REGISTRO,
                    'comentario' => 'Se registró el documento y se adjuntó el archivo PDF.',
                    'id_usuario' => $usuario->id_usuario,
                ]);

                Auditor::creacion($documento);
                Auditor::creacion($version);

                return $documento;
            });
        } catch (Throwable $e) {
            $this->disco()->delete($archivo['ruta']);

            throw $e;
        }
    }

    /** Modifica los datos del documento (no su archivo ni su estado). */
    public function actualizar(Documento $documento, array $datos): Documento
    {
        DB::transaction(function () use ($documento, $datos) {
            $documento->fill(collect($datos)->only([
                'id_tipo_documental', 'numero', 'gestion', 'fecha_emision',
                'titulo', 'descripcion', 'instancia_emisora', 'metadatos',
            ])->all())->save();

            Auditor::modificacion($documento);
        });

        return $documento;
    }

    public function agregarVersion(Documento $documento, UploadedFile $pdf, string $motivo, Usuario $usuario): VersionDocumento
    {
        $hash = hash_file('sha256', $pdf->getRealPath());

        if ($documento->versionVigente()->value('hash_sha256') === $hash) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo es idéntico a la versión vigente. Seleccione el PDF corregido.',
            ]);
        }

        $archivo = $this->guardarArchivo($pdf, $documento->gestion);

        try {
            return DB::transaction(function () use ($documento, $archivo, $motivo, $usuario) {
                // Bloquea el documento para que dos cargas simultáneas no tomen el mismo número de versión.
                Documento::whereKey($documento->id_documento)->lockForUpdate()->first();

                $numero = (int) $documento->versiones()->max('numero_version') + 1;

                // Primero se retira la vigencia de la anterior (índice único parcial: una sola vigente).
                VersionDocumento::where('id_documento', $documento->id_documento)
                    ->where('es_vigente', true)
                    ->update(['es_vigente' => false]);

                $version = $this->crearVersion($documento, $archivo, $numero, $motivo, $usuario);

                SeguimientoDocumento::create([
                    'id_documento' => $documento->id_documento,
                    'id_estado_anterior' => $documento->id_estado_actual,
                    'id_estado_nuevo' => $documento->id_estado_actual,
                    'id_version' => $version->id_version,
                    'accion' => SeguimientoDocumento::ACCION_NUEVA_VERSION,
                    'comentario' => "Versión {$numero}: {$motivo}",
                    'id_usuario' => $usuario->id_usuario,
                ]);

                $documento->touch();

                Auditor::creacion($version);

                return $version;
            });
        } catch (Throwable $e) {
            $this->disco()->delete($archivo['ruta']);

            throw $e;
        }
    }

    /**
     * Compara la huella SHA-256 actual del archivo con la registrada al cargarlo.
     *
     * @return bool|null true = íntegro, false = alterado, null = el archivo no existe
     */
    public function verificarIntegridad(VersionDocumento $version): ?bool
    {
        $disco = $this->disco();

        if (! $disco->exists($version->ruta_archivo)) {
            return null;
        }

        $flujo = $disco->readStream($version->ruta_archivo);
        $contexto = hash_init('sha256');
        hash_update_stream($contexto, $flujo);
        fclose($flujo);

        return hash_equals($version->hash_sha256, hash_final($contexto));
    }

    public function disco(): Filesystem
    {
        return Storage::disk(config('gaceta.documentos.disco'));
    }

    // ----------------------------------------------------------------- Internos

    /**
     * Valida que el contenido sea realmente un PDF y lo guarda con un nombre único:
     * documentos/{gestion}/{uuid}.pdf
     *
     * @return array{ruta: string, nombre_original: string, tamano_bytes: int, hash_sha256: string}
     */
    private function guardarArchivo(UploadedFile $pdf, int $gestion): array
    {
        $cabecera = (string) file_get_contents($pdf->getRealPath(), false, null, 0, 5);

        if ($cabecera !== '%PDF-') {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no es un PDF válido.',
            ]);
        }

        $carpeta = config('gaceta.documentos.carpeta')."/{$gestion}";
        $ruta = $pdf->storeAs($carpeta, Str::uuid().'.pdf', config('gaceta.documentos.disco'));

        if ($ruta === false) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo guardar el archivo en el servidor. Intente nuevamente.',
            ]);
        }

        return [
            'ruta' => $ruta,
            'nombre_original' => mb_substr($pdf->getClientOriginalName(), 0, 255),
            'tamano_bytes' => $pdf->getSize(),
            'hash_sha256' => hash_file('sha256', $pdf->getRealPath()),
        ];
    }

    private function crearVersion(Documento $documento, array $archivo, int $numero, string $motivo, Usuario $usuario): VersionDocumento
    {
        return VersionDocumento::create([
            'id_documento' => $documento->id_documento,
            'numero_version' => $numero,
            'nombre_original' => $archivo['nombre_original'],
            'ruta_archivo' => $archivo['ruta'],
            'tamano_bytes' => $archivo['tamano_bytes'],
            'hash_sha256' => $archivo['hash_sha256'],
            'motivo_cambio' => $motivo,
            'es_vigente' => true,
            'id_usuario_carga' => $usuario->id_usuario,
        ]);
    }
}
