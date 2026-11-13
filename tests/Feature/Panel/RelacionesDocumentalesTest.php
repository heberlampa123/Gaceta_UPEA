<?php

namespace Tests\Feature\Panel;

use App\Models\Documento;
use App\Models\EstadoDocumento;
use App\Models\RelacionDocumental;
use App\Models\Rol;
use App\Models\SeguimientoDocumento;
use App\Models\TipoDocumental;
use App\Models\TipoRelacionDocumental;
use App\Models\Usuario;
use App\Services\FlujoDocumental;
use App\Services\GestionDocumental;
use App\Support\PdfSimple;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Relaciones entre normas: una resolución nueva abroga, deroga o modifica otra publicada (13/11/2026). */
class RelacionesDocumentalesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Usuario $encargado;

    private Usuario $revisor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->encargado = Usuario::factory()->conRol(Rol::ENCARGADO)->create();
        $this->revisor = Usuario::factory()->conRol(Rol::REVISOR)->create();
    }

    /** Registra un documento real (con PDF) y lo deja en el estado indicado. */
    private function documento(string $numero, string $fecha, string $estado = EstadoDocumento::BORRADOR): Documento
    {
        $documento = app(GestionDocumental::class)->registrar([
            'id_tipo_documental' => TipoDocumental::where('codigo', TipoDocumental::RES_HCU)->value('id_tipo_documental'),
            'numero' => $numero,
            'gestion' => (int) substr($fecha, 0, 4),
            'fecha_emision' => $fecha,
            'titulo' => "Resolución {$numero}",
            'instancia_emisora' => 'Honorable Consejo Universitario',
        ], UploadedFile::fake()->createWithContent('r.pdf', PdfSimple::generar("Resolución {$numero}")), $this->encargado);

        if ($estado !== EstadoDocumento::BORRADOR) {
            $documento->forceFill([
                'id_estado_actual' => EstadoDocumento::idPorCodigo($estado),
                'fecha_publicacion' => $estado === EstadoDocumento::PUBLICADO ? now() : null,
            ])->save();
        }

        return $documento->refresh();
    }

    private function tipo(string $codigo): int
    {
        return TipoRelacionDocumental::where('codigo', $codigo)->value('id_tipo_relacion');
    }

    private function relacionar(Documento $origen, Documento $destino, string $tipo, ?string $detalle = null)
    {
        return $this->actingAs($this->encargado)
            ->from("/panel/documentos/{$origen->id_documento}")
            ->post("/panel/documentos/{$origen->id_documento}/relaciones", [
                'id_documento_destino' => $destino->id_documento,
                'id_tipo_relacion' => $this->tipo($tipo),
                'detalle' => $detalle,
            ]);
    }

    /** Recorre el flujo real hasta publicar (el revisor es distinto de quien registró). */
    private function publicar(Documento $documento): void
    {
        $flujo = app(FlujoDocumental::class);
        $flujo->enviarARevision($documento, $this->encargado);
        $flujo->aprobar($documento->refresh(), $this->revisor);
        $flujo->publicar($documento->refresh(), $this->revisor);
    }

    // ------------------------------------------------------------ Abrogación

    public function test_la_norma_abrogada_desaparece_del_portal_solo_al_publicarse_la_nueva(): void
    {
        $anterior = $this->documento('025/2026', '2026-03-10', EstadoDocumento::PUBLICADO);
        $nueva = $this->documento('045/2026', '2026-11-13');

        $this->relacionar($nueva, $anterior, TipoRelacionDocumental::ABROGA)->assertSessionHas('exito');

        // En el borrador se ve la relación pendiente y el formulario para registrar otras.
        $this->actingAs($this->encargado)->get("/panel/documentos/{$nueva->id_documento}")->assertOk()
            ->assertSee('Registrar norma afectada')->assertSee('Surtirá efecto cuando este documento se publique');
        $this->actingAs($this->encargado)->get("/panel/documentos/{$anterior->id_documento}")->assertOk()
            ->assertSee('Pendiente: esa norma está en estado')->assertDontSee('No vigente');

        // Mientras la nueva no se publica, la anterior sigue vigente y visible.
        $this->assertTrue($anterior->esVigente());
        $this->get("/documentos/{$anterior->id_documento}")->assertOk();

        $this->publicar($nueva);

        // Ahora deja de ser vigente: no aparece en el portal, ni en la búsqueda, ni en la API.
        $this->assertFalse($anterior->esVigente());
        $this->get("/documentos/{$anterior->id_documento}")->assertNotFound();
        $this->get("/documentos/{$anterior->id_documento}/pdf")->assertNotFound();
        $this->getJson('/api/documentos')->assertJsonCount(1, 'data')->assertJsonPath('data.0.numero', '045/2026');

        // La nueva la menciona sin enlace (ya no es visible al público).
        $this->get("/documentos/{$nueva->id_documento}")->assertOk()
            ->assertSee('Abroga')->assertSee('025/2026')->assertSee('(sin vigencia)');

        // En el panel se conserva, marcada como no vigente, con constancia en su seguimiento.
        $this->actingAs($this->encargado)->get("/panel/documentos/{$anterior->id_documento}")
            ->assertOk()->assertSee('No vigente')->assertSee('Abrogada por');
        $this->actingAs($this->encargado)->get('/panel/documentos')->assertSee('No vigente');
        $this->assertDatabaseHas('seguimiento_documento', [
            'id_documento' => $anterior->id_documento,
            'accion' => SeguimientoDocumento::ACCION_AFECTACION,
        ]);
        $this->assertSame(EstadoDocumento::PUBLICADO, $anterior->refresh()->estadoActual->codigo);
    }

    // ------------------------------------------------------------ Derogación parcial y modificación

    public function test_la_norma_derogada_parcialmente_sigue_vigente_y_muestra_el_aviso(): void
    {
        $anterior = $this->documento('010/2026', '2026-02-01', EstadoDocumento::PUBLICADO);
        $nueva = $this->documento('050/2026', '2026-11-13');

        // Derogar exige indicar qué parte se afecta.
        $this->relacionar($nueva, $anterior, TipoRelacionDocumental::DEROGA)->assertSessionHasErrors('detalle');
        $this->relacionar($nueva, $anterior, TipoRelacionDocumental::DEROGA, 'Artículos 3 y 5')->assertSessionHas('exito');

        $this->publicar($nueva);

        $this->assertTrue($anterior->esVigente());
        $this->get("/documentos/{$anterior->id_documento}")->assertOk()
            ->assertSee('Derogada parcialmente por')->assertSee('050/2026')->assertSee('Artículos 3 y 5');
        $this->getJson('/api/documentos')->assertJsonCount(2, 'data');
    }

    // ------------------------------------------------------------ Reglas

    public function test_reglas_de_la_norma_afectada(): void
    {
        $nueva = $this->documento('060/2026', '2026-06-01');
        $borrador = $this->documento('020/2026', '2026-01-15');
        $posterior = $this->documento('070/2026', '2026-07-01', EstadoDocumento::PUBLICADO);
        $publicada = $this->documento('030/2026', '2026-04-01', EstadoDocumento::PUBLICADO);

        // Debe estar publicada, ser anterior y no repetirse.
        $this->relacionar($nueva, $borrador, TipoRelacionDocumental::ABROGA)->assertSessionHasErrors('id_documento_destino');
        $this->relacionar($nueva, $posterior, TipoRelacionDocumental::ABROGA)->assertSessionHasErrors('id_documento_destino');
        $this->relacionar($nueva, $nueva, TipoRelacionDocumental::ABROGA)->assertSessionHasErrors('id_documento_destino');
        $this->relacionar($nueva, $publicada, TipoRelacionDocumental::MODIFICA, 'Artículo 2')->assertSessionHas('exito');
        $this->relacionar($nueva, $publicada, TipoRelacionDocumental::ABROGA)->assertSessionHasErrors('id_documento_destino');
        $this->assertSame(1, RelacionDocumental::count());

        // Una norma ya abrogada no puede volver a afectarse.
        $abrogada = $this->documento('005/2026', '2026-01-05', EstadoDocumento::PUBLICADO);
        $abrogante = $this->documento('006/2026', '2026-01-06', EstadoDocumento::PUBLICADO);
        RelacionDocumental::create(['id_documento_origen' => $abrogante->id_documento, 'id_documento_destino' => $abrogada->id_documento,
            'id_tipo_relacion' => $this->tipo(TipoRelacionDocumental::ABROGA), 'id_usuario_registro' => $this->encargado->id_usuario]);
        $this->relacionar($nueva, $abrogada, TipoRelacionDocumental::MODIFICA, 'Art. 1')->assertSessionHasErrors('id_documento_destino');
    }

    public function test_solo_se_gestionan_mientras_el_documento_es_editable_y_por_quien_puede_editar(): void
    {
        $anterior = $this->documento('011/2026', '2026-02-01', EstadoDocumento::PUBLICADO);
        $nueva = $this->documento('051/2026', '2026-11-13');
        $this->relacionar($nueva, $anterior, TipoRelacionDocumental::ABROGA)->assertSessionHas('exito');
        $relacion = RelacionDocumental::firstOrFail();

        // El revisor no tiene permiso de edición.
        $this->actingAs($this->revisor)->post("/panel/documentos/{$nueva->id_documento}/relaciones", [
            'id_documento_destino' => $anterior->id_documento, 'id_tipo_relacion' => $this->tipo(TipoRelacionDocumental::ABROGA),
        ])->assertForbidden();

        // En revisión ya no se puede agregar ni quitar.
        app(FlujoDocumental::class)->enviarARevision($nueva, $this->encargado);
        $this->actingAs($this->encargado)->delete("/panel/documentos/{$nueva->id_documento}/relaciones/{$relacion->id_relacion}")->assertForbidden();

        // De nuevo editable (observado): se puede quitar.
        app(FlujoDocumental::class)->devolver($nueva->refresh(), $this->revisor, ['Revisar la relación']);
        $this->actingAs($this->encargado)->delete("/panel/documentos/{$nueva->id_documento}/relaciones/{$relacion->id_relacion}")
            ->assertSessionHas('exito');
        $this->assertSame(0, RelacionDocumental::count());
    }

    public function test_el_buscador_ofrece_solo_normas_publicadas_vigentes_y_anteriores(): void
    {
        $nueva = $this->documento('080/2026', '2026-08-01');
        $this->documento('081/2026', '2026-03-01', EstadoDocumento::PUBLICADO);
        $this->documento('082/2026', '2026-03-02'); // borrador
        $this->documento('083/2026', '2026-09-01', EstadoDocumento::PUBLICADO); // posterior

        $this->actingAs($this->encargado)->getJson("/panel/documentos/{$nueva->id_documento}/relaciones/candidatos?q=/2026")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Resolución del HCU N.º 081/2026');
    }

    // ------------------------------------------------------------ Desactivación

    public function test_al_deshabilitar_la_funcion_la_norma_abrogada_vuelve_a_ser_visible(): void
    {
        $anterior = $this->documento('012/2026', '2026-02-01', EstadoDocumento::PUBLICADO);
        $nueva = $this->documento('052/2026', '2026-11-13');
        $this->relacionar($nueva, $anterior, TipoRelacionDocumental::ABROGA);
        $this->publicar($nueva);
        $this->get("/documentos/{$anterior->id_documento}")->assertNotFound();

        config(['gaceta.relaciones.habilitado' => false]);

        $this->get("/documentos/{$anterior->id_documento}")->assertOk()->assertDontSee('Relación con otras normas');
        $this->actingAs($this->encargado)->get("/panel/documentos/{$anterior->id_documento}")->assertOk()->assertDontSee('No vigente');
        $this->actingAs($this->encargado)->post("/panel/documentos/{$nueva->id_documento}/relaciones", [])->assertNotFound();
    }
}
