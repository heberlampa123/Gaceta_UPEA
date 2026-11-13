<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use App\Support\PdfSimple;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class SeguridadYRespaldoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    // ------------------------------------------------------------ Cabeceras y páginas de error

    public function test_todas_las_respuestas_llevan_cabeceras_de_seguridad(): void
    {
        foreach (['/', '/login', '/api/documentos'] as $url) {
            $this->get($url)
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    }

    public function test_las_paginas_de_error_estan_en_espanol(): void
    {
        $this->get('/documentos/999999')->assertNotFound()->assertSee('Página no encontrada');

        $encargado = Usuario::factory()->conRol(Rol::ENCARGADO)->create();
        $this->actingAs($encargado)->get('/panel/usuarios')
            ->assertForbidden()
            ->assertSee('Acceso no permitido')
            ->assertSee('No tiene permiso para realizar esta acción.')
            ->assertDontSee('This action is unauthorized');
    }

    // ------------------------------------------------------------ Respaldo

    public function test_el_respaldo_guarda_la_base_de_datos_los_pdf_y_un_resumen(): void
    {
        $carpeta = storage_path('framework/testing/respaldos');
        File::deleteDirectory($carpeta);
        config(['gaceta.respaldos.carpeta' => $carpeta, 'gaceta.respaldos.contenedor_docker' => 'gaceta_postgres']);

        Storage::fake('local');
        Storage::disk('local')->put('documentos/2026/a.pdf', PdfSimple::generar('A'));
        Storage::disk('local')->put('documentos/2026/b.pdf', PdfSimple::generar('B'));

        Process::fake(['*' => Process::result(output: 'PGDMP-contenido-simulado')]);

        $this->artisan('gaceta:respaldo')->assertSuccessful()->run();

        Process::assertRan(fn (PendingProcess $proceso) => in_array('pg_dump', $proceso->command, true)
            && in_array('--format=custom', $proceso->command, true));

        $respaldo = File::directories($carpeta)[0];
        $this->assertSame('PGDMP-contenido-simulado', trim(File::get($respaldo.'/base_datos.dump'))); // la simulación agrega un salto de línea

        $zip = new ZipArchive;
        $zip->open($respaldo.'/documentos.zip');
        $this->assertSame(2, $zip->numFiles);
        $this->assertNotFalse($zip->locateName('documentos/2026/a.pdf'));
        $zip->close();

        $resumen = json_decode(File::get($respaldo.'/respaldo.json'), true);
        $this->assertSame(2, $resumen['pdf_respaldados']);
        $this->assertSame(hash_file('sha256', $respaldo.'/base_datos.dump'), $resumen['archivos']['base_datos.dump']['sha256']);

        File::deleteDirectory($carpeta);
    }

    public function test_el_respaldo_falla_sin_dejar_archivos_si_pg_dump_falla_y_elimina_los_antiguos(): void
    {
        $carpeta = storage_path('framework/testing/respaldos');
        File::deleteDirectory($carpeta);
        config(['gaceta.respaldos.carpeta' => $carpeta, 'gaceta.respaldos.dias_retencion' => 30]);

        // Respaldo antiguo (40 días) que debe eliminarse.
        $antiguo = $carpeta.'/2026-01-01_020000';
        File::ensureDirectoryExists($antiguo);
        touch($antiguo, now()->subDays(40)->getTimestamp());

        Process::fake(['*' => Process::result(output: '', errorOutput: 'conexión rechazada', exitCode: 1)]);
        $this->artisan('gaceta:respaldo --sin-archivos')->assertFailed()->run();
        // El fallo no deja carpetas nuevas (se comparan las rutas con el mismo separador).
        $normalizar = fn (string $ruta) => str_replace('\\', '/', $ruta);
        $this->assertSame([$normalizar($antiguo)], array_map($normalizar, File::directories($carpeta)));

        Process::fake(['*' => Process::result(output: 'PGDMP')]);
        $this->artisan('gaceta:respaldo --sin-archivos')->assertSuccessful()->run();

        $restantes = File::directories($carpeta);
        $this->assertCount(1, $restantes);
        $this->assertDirectoryDoesNotExist($antiguo);
        $this->assertFileDoesNotExist($restantes[0].'/documentos.zip');

        File::deleteDirectory($carpeta);
    }
}
