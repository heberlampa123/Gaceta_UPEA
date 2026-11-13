<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Respaldo de la base de datos y de los archivos PDF (Fase 8).
 *
 *   php artisan gaceta:respaldo                 → respaldo completo
 *   php artisan gaceta:respaldo --sin-archivos  → solo la base de datos
 *
 * Crea en config('gaceta.respaldos.carpeta') una carpeta AAAA-MM-DD_HHMMSS con:
 *   - base_datos.dump   (pg_dump en formato personalizado; se restaura con pg_restore)
 *   - documentos.zip    (los PDF de storage/app/private/documentos)
 *   - respaldo.json     (fecha, tamaños y huellas SHA-256 para verificar el respaldo)
 * y elimina los respaldos con más días que config('gaceta.respaldos.dias_retencion').
 *
 * Se programa a diario en routes/console.php (requiere el programador de tareas del servidor).
 */
class RespaldoGaceta extends Command
{
    protected $signature = 'gaceta:respaldo {--sin-archivos : Respalda solo la base de datos}';

    protected $description = 'Respalda la base de datos y los PDF de la Gaceta, y elimina los respaldos antiguos';

    public function handle(): int
    {
        $destino = rtrim(config('gaceta.respaldos.carpeta'), '\\/').DIRECTORY_SEPARATOR.now()->format('Y-m-d_His');
        File::ensureDirectoryExists($destino);

        try {
            $this->components->task('Base de datos', fn () => $this->respaldarBaseDatos($destino.DIRECTORY_SEPARATOR.'base_datos.dump'));

            $pdfs = 0;
            if (! $this->option('sin-archivos')) {
                $this->components->task('Archivos PDF', function () use ($destino, &$pdfs) {
                    $pdfs = $this->respaldarArchivos($destino.DIRECTORY_SEPARATOR.'documentos.zip');
                });
            }

            $this->escribirResumen($destino, $pdfs);
        } catch (RuntimeException $e) {
            File::deleteDirectory($destino);
            $this->components->error('El respaldo falló: '.$e->getMessage());

            return self::FAILURE;
        }

        $eliminados = $this->eliminarAntiguos();

        $this->components->info("Respaldo creado en: {$destino}");
        if ($eliminados > 0) {
            $this->components->info("Se eliminaron {$eliminados} respaldo(s) con más de ".config('gaceta.respaldos.dias_retencion').' días.');
        }

        return self::SUCCESS;
    }

    private function respaldarBaseDatos(string $archivo): void
    {
        $bd = config('database.connections.'.config('database.default'));
        $contenedor = config('gaceta.respaldos.contenedor_docker');

        // Con Docker se usa el pg_dump del contenedor; en producción, el instalado en el servidor.
        $comando = $contenedor
            ? ['docker', 'exec', '-e', 'PGPASSWORD', $contenedor, 'pg_dump', '-U', $bd['username'], '-d', $bd['database'], '--format=custom', '--no-owner']
            : [config('gaceta.respaldos.pg_dump'), '-h', $bd['host'], '-p', (string) $bd['port'], '-U', $bd['username'], '-d', $bd['database'], '--format=custom', '--no-owner'];

        $resultado = Process::timeout(900)->env(['PGPASSWORD' => $bd['password']])->run($comando);

        if ($resultado->failed() || $resultado->output() === '') {
            throw new RuntimeException('pg_dump no pudo respaldar la base de datos. '.trim($resultado->errorOutput()));
        }

        File::put($archivo, $resultado->output());
    }

    /** @return int cantidad de PDF respaldados */
    private function respaldarArchivos(string $archivoZip): int
    {
        $disco = Storage::disk(config('gaceta.documentos.disco'));
        $carpeta = config('gaceta.documentos.carpeta');

        $zip = new ZipArchive;
        if ($zip->open($archivoZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo ZIP de los documentos.');
        }

        $cantidad = 0;
        foreach ($disco->allFiles($carpeta) as $ruta) {
            $zip->addFile($disco->path($ruta), $ruta);
            $cantidad++;
        }

        if ($cantidad === 0) {
            $zip->addFromString('LEEME.txt', 'No había archivos PDF al momento del respaldo.');
        }

        $zip->close();

        return $cantidad;
    }

    private function escribirResumen(string $destino, int $pdfs): void
    {
        $archivos = collect(File::files($destino))->mapWithKeys(fn ($f) => [$f->getFilename() => [
            'bytes' => $f->getSize(),
            'sha256' => hash_file('sha256', $f->getPathname()),
        ]]);

        File::put($destino.DIRECTORY_SEPARATOR.'respaldo.json', json_encode([
            'sistema' => config('app.name'),
            'fecha' => now()->toIso8601String(),
            'base_datos' => config('database.connections.'.config('database.default').'.database'),
            'pdf_respaldados' => $pdfs,
            'archivos' => $archivos,
            'restaurar' => 'pg_restore --clean --if-exists --no-owner -d <base> base_datos.dump ; descomprimir documentos.zip en storage/app/private/',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function eliminarAntiguos(): int
    {
        $limite = now()->subDays(config('gaceta.respaldos.dias_retencion'))->getTimestamp();
        $eliminados = 0;

        foreach (File::directories(config('gaceta.respaldos.carpeta')) as $carpeta) {
            if (preg_match('/\d{4}-\d{2}-\d{2}_\d{6}$/', $carpeta) && File::lastModified($carpeta) < $limite) {
                File::deleteDirectory($carpeta);
                $eliminados++;
            }
        }

        return $eliminados;
    }
}
