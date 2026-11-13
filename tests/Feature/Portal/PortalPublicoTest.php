<?php

namespace Tests\Feature\Portal;

use App\Models\Documento;
use App\Models\EstadoDocumento;
use App\Models\Rol;
use App\Models\TipoDocumental;
use App\Models\Usuario;
use App\Services\GestionDocumental;
use App\Support\PdfSimple;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalPublicoTest extends TestCase
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

    /** Registra un documento real (con PDF) y lo deja en el estado indicado. */
    private function documento(array $datos = [], string $estado = EstadoDocumento::PUBLICADO): Documento
    {
        $datos = [
            'id_tipo_documental' => TipoDocumental::where('codigo', TipoDocumental::RES_HCU)->value('id_tipo_documental'),
            'numero' => '001/2026',
            'gestion' => 2026,
            'fecha_emision' => '2026-03-15',
            'titulo' => 'Resolución de prueba',
            'descripcion' => 'Descripción de prueba.',
            'instancia_emisora' => 'Honorable Consejo Universitario',
            ...$datos,
        ];

        $documento = app(GestionDocumental::class)->registrar(
            $datos,
            UploadedFile::fake()->createWithContent('r.pdf', PdfSimple::generar("Resolución {$datos['numero']}")),
            $this->encargado,
        );

        $documento->forceFill([
            'id_estado_actual' => EstadoDocumento::idPorCodigo($estado),
            'fecha_publicacion' => $estado === EstadoDocumento::PUBLICADO ? now() : null,
        ])->save();

        return $documento;
    }

    // ------------------------------------------------------------ Visibilidad

    public function test_el_portal_es_publico_y_solo_muestra_documentos_publicados(): void
    {
        $this->documento(['numero' => '101/2026', 'titulo' => 'Reglamento publicado']);
        $this->documento(['numero' => '102/2026'], EstadoDocumento::BORRADOR);
        $this->documento(['numero' => '103/2026'], EstadoDocumento::APROBADO);
        $this->documento(['numero' => '104/2026'])->forceFill(['activo' => false])->save();

        $this->assertGuest();
        $this->get('/')
            ->assertOk()
            ->assertSee('101/2026')
            ->assertSee('Reglamento publicado')
            ->assertDontSee('102/2026')
            ->assertDontSee('103/2026')
            ->assertDontSee('104/2026')
            ->assertSee('1 documento(s) encontrado(s)');
    }

    public function test_la_ficha_y_el_pdf_solo_existen_para_documentos_publicados(): void
    {
        $publicado = $this->documento(['numero' => '201/2026']);
        $borrador = $this->documento(['numero' => '202/2026'], EstadoDocumento::BORRADOR);

        $this->get("/documentos/{$publicado->id_documento}")
            ->assertOk()
            ->assertSee('201/2026')
            ->assertSee($publicado->versionVigente->hash_sha256);

        $this->get("/documentos/{$publicado->id_documento}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=res-hcu-201-2026.pdf');

        $this->get("/documentos/{$publicado->id_documento}/pdf?descargar=1")
            ->assertHeader('Content-Disposition', 'attachment; filename=res-hcu-201-2026.pdf');

        $this->get("/documentos/{$borrador->id_documento}")->assertNotFound();
        $this->get("/documentos/{$borrador->id_documento}/pdf")->assertNotFound();
        $this->get('/documentos/999999')->assertNotFound();
    }

    public function test_el_pdf_publico_es_la_version_vigente(): void
    {
        $documento = $this->documento(['numero' => '301/2026']);
        $documento->forceFill(['id_estado_actual' => EstadoDocumento::idPorCodigo(EstadoDocumento::OBSERVADO)])->save();
        app(GestionDocumental::class)->agregarVersion($documento, UploadedFile::fake()->createWithContent('v2.pdf', PdfSimple::generar('Versión corregida')), 'Corrección', $this->encargado);
        $documento->forceFill(['id_estado_actual' => EstadoDocumento::idPorCodigo(EstadoDocumento::PUBLICADO)])->save();

        $contenido = $this->get("/documentos/{$documento->id_documento}/pdf")->streamedContent();

        $this->assertSame(hash('sha256', $contenido), $documento->versionVigente()->value('hash_sha256'));
        $this->assertSame(2, $documento->versionVigente()->value('numero_version'));
    }

    // ------------------------------------------------------------ Búsqueda y filtros

    public function test_los_filtros_del_portal_funcionan_sin_javascript(): void
    {
        $this->documento(['numero' => '010/2025', 'gestion' => 2025, 'fecha_emision' => '2025-06-01', 'titulo' => 'Reglamento de Titulación']);
        $this->documento(['numero' => '020/2026', 'gestion' => 2026, 'fecha_emision' => '2026-02-10', 'titulo' => 'Convocatoria docente', 'instancia_emisora' => 'Rectorado']);

        $this->get('/?q=reglamento')->assertSee('010/2025')->assertDontSee('020/2026');
        $this->get('/?gestion=2026')->assertSee('020/2026')->assertDontSee('010/2025');
        $this->get('/?numero=010')->assertSee('010/2025')->assertDontSee('020/2026');
        $this->get('/?desde=2026-01-01&hasta=2026-12-31')->assertSee('020/2026')->assertDontSee('010/2025');
        $this->get('/?instancia=Rectorado')->assertSee('020/2026')->assertDontSee('010/2025');
        $this->get('/?q=inexistente')->assertSee('No se encontraron documentos');

        // Filtros inválidos se ignoran en lugar de producir un error.
        $this->get('/?gestion=abc&desde=2026-13-45&orden=x&tipo=-1')->assertOk()->assertSee('010/2025')->assertSee('020/2026');
    }

    public function test_la_api_publica_devuelve_json_con_filtros_y_paginacion(): void
    {
        foreach (range(1, 12) as $i) {
            $this->documento(['numero' => sprintf('%03d/2026', $i), 'fecha_emision' => sprintf('2026-01-%02d', $i)]);
        }
        $this->documento(['numero' => '500/2026'], EstadoDocumento::BORRADOR);

        $respuesta = $this->getJson('/api/documentos')->assertOk();
        $respuesta->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.numero', '012/2026') // más recientes primero
            ->assertJsonStructure(['data' => [['id', 'tipo', 'numero', 'gestion', 'titulo', 'resumen', 'instancia_emisora',
                'fecha_emision', 'fecha_emision_texto', 'url', 'url_pdf', 'url_descarga']], 'meta', 'links', 'filtros']);

        // Nunca expone datos internos.
        $json = $respuesta->getContent();
        foreach (['ruta_archivo', 'id_usuario', 'id_estado', 'contrasena', 'documentos/2026'] as $interno) {
            $this->assertStringNotContainsString($interno, $json);
        }

        $this->getJson('/api/documentos?page=2')->assertJsonCount(2, 'data');
        $this->getJson('/api/documentos?numero=007')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.numero', '007/2026');
        $this->getJson('/api/documentos?orden=antiguos')->assertJsonPath('data.0.numero', '001/2026');
        $this->getJson('/api/documentos?numero=500')->assertJsonPath('meta.total', 0); // borrador: no aparece
    }

    public function test_la_api_tiene_limite_de_consultas_por_ip(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/documentos')->assertOk();
        }

        $this->getJson('/api/documentos')->assertStatus(429);
    }

    public function test_los_campos_adicionales_se_muestran_en_la_ficha_publica(): void
    {
        $tipo = TipoDocumental::create([
            'codigo' => 'RES_RECTORAL',
            'nombre' => 'Resolución Rectoral',
            'activo' => true,
            'campos_adicionales' => [['nombre' => 'tipo_sesion', 'etiqueta' => 'Tipo de sesión', 'tipo' => 'seleccion', 'obligatorio' => true, 'opciones' => ['Ordinaria', 'Extraordinaria']]],
        ]);

        $documento = $this->documento(['id_tipo_documental' => $tipo->id_tipo_documental, 'numero' => '050/2026', 'metadatos' => ['tipo_sesion' => 'Extraordinaria']]);

        $this->get("/documentos/{$documento->id_documento}")
            ->assertOk()
            ->assertSee('Resolución Rectoral')
            ->assertSee('Tipo de sesión')
            ->assertSee('Extraordinaria');

        $this->get('/?tipo='.$tipo->id_tipo_documental)->assertSee('050/2026');
    }
}
