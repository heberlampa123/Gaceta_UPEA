<?php

namespace Tests\Feature\Panel;

use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\EstadoDocumento;
use App\Models\Rol;
use App\Models\SeguimientoDocumento;
use App\Models\TipoDocumental;
use App\Models\Usuario;
use App\Models\VersionDocumento;
use App\Support\PdfSimple;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GestionDocumentalTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Usuario $encargado;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->encargado = Usuario::factory()->conRol(Rol::ENCARGADO)->create();
    }

    private function pdf(string $texto = 'Resolución de prueba', string $nombre = 'resolucion.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, PdfSimple::generar($texto));
    }

    private function datos(array $cambios = []): array
    {
        return [
            'id_tipo_documental' => TipoDocumental::where('codigo', TipoDocumental::RES_HCU)->value('id_tipo_documental'),
            'numero' => '025/2026',
            'gestion' => 2026,
            'fecha_emision' => '2026-11-10',
            'titulo' => 'Resolución que aprueba el Reglamento de Titulación',
            'descripcion' => 'Modalidades de graduación.',
            'instancia_emisora' => 'Honorable Consejo Universitario',
            'archivo' => $this->pdf(),
            ...$cambios,
        ];
    }

    private function registrar(array $cambios = []): Documento
    {
        $this->actingAs($this->encargado)->post('/panel/documentos', $this->datos($cambios))->assertRedirect();
        $this->flushSession(); // descarta el mensaje flash para no afectar la siguiente petición

        return Documento::latest('id_documento')->firstOrFail();
    }

    // ------------------------------------------------------------ Registro

    public function test_el_encargado_registra_un_documento_con_su_pdf(): void
    {
        $pdf = $this->pdf();
        $hash = hash_file('sha256', $pdf->getRealPath());

        $this->actingAs($this->encargado)
            ->post('/panel/documentos', $this->datos(['archivo' => $pdf]))
            ->assertSessionHasNoErrors();

        $documento = Documento::with('estadoActual', 'versionVigente')->firstOrFail();

        $this->assertSame(EstadoDocumento::BORRADOR, $documento->estadoActual->codigo);
        $this->assertSame($this->encargado->id_usuario, $documento->id_usuario_registro);

        $version = $documento->versionVigente;
        $this->assertSame(1, $version->numero_version);
        $this->assertSame($hash, $version->hash_sha256);
        $this->assertSame('resolucion.pdf', $version->nombre_original);
        $this->assertStringStartsWith('documentos/2026/', $version->ruta_archivo);
        Storage::disk('local')->assertExists($version->ruta_archivo);

        $this->assertDatabaseHas('seguimiento_documento', [
            'id_documento' => $documento->id_documento,
            'accion' => SeguimientoDocumento::ACCION_REGISTRO,
            'id_estado_anterior' => null,
            'id_version' => $version->id_version,
        ]);
        $this->assertDatabaseHas('auditoria', ['accion' => Auditoria::CREAR, 'entidad' => 'Documento', 'id_registro' => $documento->id_documento]);
        $this->assertDatabaseHas('auditoria', ['accion' => Auditoria::CREAR, 'entidad' => 'VersionDocumento', 'id_registro' => $version->id_version]);
    }

    public function test_solo_quien_tiene_permiso_puede_registrar(): void
    {
        $revisor = Usuario::factory()->conRol(Rol::REVISOR)->create();

        $this->actingAs($revisor)->get('/panel/documentos/registrar')->assertForbidden();
        $this->actingAs($revisor)->post('/panel/documentos', $this->datos())->assertForbidden();
        $this->actingAs($revisor)->get('/panel/documentos')->assertOk(); // sí puede consultar

        $this->assertSame(0, Documento::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_no_se_repite_el_numero_en_la_misma_gestion(): void
    {
        $this->registrar();

        $this->actingAs($this->encargado)
            ->post('/panel/documentos', $this->datos())
            ->assertSessionHasErrors('numero');

        // Mismo número en otra gestión: permitido.
        $this->actingAs($this->encargado)
            ->post('/panel/documentos', $this->datos(['gestion' => 2025, 'fecha_emision' => '2025-05-01']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Documento::count());
    }

    public function test_solo_se_aceptan_pdf_validos_de_hasta_20_mb(): void
    {
        $falso = UploadedFile::fake()->createWithContent('virus.pdf', 'MZ esto no es un PDF');
        $grande = UploadedFile::fake()->create('grande.pdf', 20 * 1024 + 1, 'application/pdf');
        $word = UploadedFile::fake()->create('documento.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        foreach ([$falso, $grande, $word] as $archivo) {
            $this->actingAs($this->encargado)
                ->post('/panel/documentos', $this->datos(['archivo' => $archivo]))
                ->assertSessionHasErrors('archivo');
        }

        $this->assertSame(0, Documento::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ------------------------------------------------------------ Versiones

    public function test_una_nueva_version_reemplaza_a_la_vigente_sin_borrarla(): void
    {
        $documento = $this->registrar();
        $v1 = $documento->versionVigente;

        $this->actingAs($this->encargado)
            ->post("/panel/documentos/{$documento->id_documento}/versiones", [
                'archivo' => $this->pdf('Resolución corregida'),
                'motivo_cambio' => 'Corrección del número en el encabezado',
            ])
            ->assertSessionHasNoErrors();

        $versiones = $documento->versiones()->get();
        $this->assertCount(2, $versiones);
        $this->assertFalse($versiones[0]->es_vigente);
        $this->assertTrue($versiones[1]->es_vigente);
        $this->assertSame(2, $versiones[1]->numero_version);

        Storage::disk('local')->assertExists($v1->ruta_archivo);
        Storage::disk('local')->assertExists($versiones[1]->ruta_archivo);

        $this->assertDatabaseHas('seguimiento_documento', [
            'id_documento' => $documento->id_documento,
            'accion' => SeguimientoDocumento::ACCION_NUEVA_VERSION,
            'id_version' => $versiones[1]->id_version,
        ]);
    }

    public function test_la_nueva_version_exige_motivo_y_un_archivo_distinto(): void
    {
        $documento = $this->registrar(['archivo' => $this->pdf('Mismo contenido')]);
        $url = "/panel/documentos/{$documento->id_documento}/versiones";

        $this->actingAs($this->encargado)->post($url, ['archivo' => $this->pdf('Otro')])
            ->assertSessionHasErrors('motivo_cambio');

        $this->actingAs($this->encargado)->post($url, ['archivo' => $this->pdf('Mismo contenido'), 'motivo_cambio' => 'Sin cambios reales'])
            ->assertSessionHasErrors('archivo');

        $this->assertSame(1, $documento->versiones()->count());
    }

    // ------------------------------------------------------------ Edición según el estado

    public function test_solo_se_edita_en_borrador_u_observado(): void
    {
        $documento = $this->registrar();
        $datosEdicion = collect($this->datos(['titulo' => 'Título corregido']))->except('archivo')->all();

        $this->actingAs($this->encargado)
            ->put("/panel/documentos/{$documento->id_documento}", $datosEdicion)
            ->assertRedirect("/panel/documentos/{$documento->id_documento}");

        $this->assertSame('Título corregido', $documento->refresh()->titulo);
        $detalle = Auditoria::where('accion', Auditoria::MODIFICAR)->where('entidad', 'Documento')->value('detalle');
        $this->assertSame('Título corregido', $detalle['nuevo']['titulo']);

        foreach ([EstadoDocumento::PENDIENTE_REVISION, EstadoDocumento::APROBADO, EstadoDocumento::PUBLICADO] as $estado) {
            $documento->forceFill(['id_estado_actual' => EstadoDocumento::idPorCodigo($estado)])->save();

            $this->actingAs($this->encargado)->get("/panel/documentos/{$documento->id_documento}/editar")->assertForbidden();
            $this->actingAs($this->encargado)->put("/panel/documentos/{$documento->id_documento}", $datosEdicion)->assertForbidden();
            $this->actingAs($this->encargado)->post("/panel/documentos/{$documento->id_documento}/versiones", [
                'archivo' => $this->pdf('Cambio no permitido'),
                'motivo_cambio' => 'Intento de cambio',
            ])->assertForbidden();
        }

        $documento->forceFill(['id_estado_actual' => EstadoDocumento::idPorCodigo(EstadoDocumento::OBSERVADO)])->save();
        $this->actingAs($this->encargado)->get("/panel/documentos/{$documento->id_documento}/editar")->assertOk();
    }

    // ------------------------------------------------------------ Consulta y archivos

    public function test_el_pdf_se_muestra_y_descarga_solo_desde_el_panel(): void
    {
        $documento = $this->registrar();
        $version = $documento->versionVigente;
        $url = "/panel/documentos/{$documento->id_documento}/versiones/{$version->id_version}/archivo";

        $this->actingAs($this->encargado)->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=res-hcu-025-2026_v1.pdf');

        $this->actingAs($this->encargado)->get($url.'?descargar=1')
            ->assertHeader('Content-Disposition', 'attachment; filename=res-hcu-025-2026_v1.pdf');

        // Versión de otro documento: no se entrega.
        $otro = $this->registrar(['numero' => '026/2026']);
        $this->actingAs($this->encargado)
            ->get("/panel/documentos/{$otro->id_documento}/versiones/{$version->id_version}/archivo")
            ->assertNotFound();

        auth()->logout();
        $this->get($url)->assertRedirect('/login');
    }

    public function test_la_verificacion_de_integridad_detecta_archivos_alterados(): void
    {
        $documento = $this->registrar();
        $version = $documento->versionVigente;
        $url = "/panel/documentos/{$documento->id_documento}/versiones/{$version->id_version}/verificar";

        $this->actingAs($this->encargado)->post($url)->assertSessionHas('exito');

        Storage::disk('local')->put($version->ruta_archivo, PdfSimple::generar('Contenido alterado'));
        $this->actingAs($this->encargado)->post($url)->assertSessionHas('error');

        Storage::disk('local')->delete($version->ruta_archivo);
        $this->actingAs($this->encargado)->post($url)->assertSessionHas('error');
    }

    public function test_el_listado_filtra_y_busca_documentos(): void
    {
        $this->registrar(['numero' => '101/2026', 'titulo' => 'Reglamento de Titulación']);
        $this->registrar(['numero' => '102/2026', 'titulo' => 'Convocatoria docente', 'descripcion' => 'Carrera de Derecho.']);

        $this->actingAs($this->encargado)->get('/panel/documentos?buscar=reglamento')
            ->assertOk()->assertSee('101/2026')->assertDontSee('102/2026');

        $this->actingAs($this->encargado)->get('/panel/documentos?buscar=102/2026')
            ->assertSee('102/2026')->assertDontSee('101/2026');

        $this->actingAs($this->encargado)->get('/panel/documentos?estado=PUBLICADO')
            ->assertDontSee('101/2026')->assertSee('No se encontraron documentos');

        $documento = Documento::where('numero', '101/2026')->firstOrFail();
        $this->actingAs($this->encargado)->get("/panel/documentos/{$documento->id_documento}")
            ->assertOk()
            ->assertSee('Registro del documento')
            ->assertSee('Versión inicial');
    }

    public function test_la_version_vigente_es_unica_tras_varias_cargas(): void
    {
        $documento = $this->registrar();

        foreach (['A', 'B', 'C'] as $letra) {
            $this->actingAs($this->encargado)->post("/panel/documentos/{$documento->id_documento}/versiones", [
                'archivo' => $this->pdf("Versión {$letra}"),
                'motivo_cambio' => "Corrección {$letra}",
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(1, VersionDocumento::where('id_documento', $documento->id_documento)->where('es_vigente', true)->count());
        $this->assertSame(4, $documento->versionVigente()->value('numero_version'));
    }
}
