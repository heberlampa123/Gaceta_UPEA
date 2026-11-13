<?php

namespace Tests\Feature\Panel;

use App\Events\DocumentoCambioDeEstado;
use App\Exceptions\TransicionNoPermitida;
use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\EstadoDocumento;
use App\Models\Observacion;
use App\Models\Rol;
use App\Models\SeguimientoDocumento;
use App\Models\Usuario;
use App\Services\FlujoDocumental;
use App\Services\GestionDocumental;
use App\Support\PdfSimple;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FlujoDocumentalTest extends TestCase
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

    private function pdf(string $texto): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('resolucion.pdf', PdfSimple::generar($texto));
    }

    /** Documento registrado por el encargado mediante el servicio real (estado Borrador, con PDF v1). */
    private function documento(?Usuario $registradoPor = null): Documento
    {
        $datos = Documento::factory()->make()->only(['id_tipo_documental', 'numero', 'gestion', 'titulo', 'instancia_emisora']);
        $datos['fecha_emision'] = '2026-11-01';

        return app(GestionDocumental::class)->registrar($datos, $this->pdf('Resolución '.$datos['numero']), $registradoPor ?? $this->encargado);
    }

    private function estado(Documento $documento): string
    {
        return $documento->refresh()->estadoActual->codigo;
    }

    private function url(Documento $documento, string $accion): string
    {
        return "/panel/documentos/{$documento->id_documento}/{$accion}";
    }

    // ------------------------------------------------------------ Recorrido completo

    public function test_recorrido_completo_registro_revision_observacion_aprobacion_y_publicacion(): void
    {
        Event::fake([DocumentoCambioDeEstado::class]);
        $documento = $this->documento();

        // 1. El encargado envía a revisión.
        $this->actingAs($this->encargado)->post($this->url($documento, 'enviar-revision'), ['comentario' => 'Listo para revisar'])
            ->assertSessionHas('exito');
        $this->assertSame(EstadoDocumento::PENDIENTE_REVISION, $this->estado($documento));

        // 2. El revisor devuelve con dos observaciones (las vacías se ignoran).
        $this->actingAs($this->revisor)->post($this->url($documento, 'devolver'), [
            'observaciones' => ['El número no coincide con el PDF.', '', 'Falta la firma.'],
        ])->assertRedirect('/panel/revision');
        $this->assertSame(EstadoDocumento::OBSERVADO, $this->estado($documento));
        $this->assertSame(2, $documento->observaciones()->pendientes()->count());

        // 3. El encargado corrige el PDF (nueva versión) y responde las observaciones.
        $this->actingAs($this->encargado)->post("/panel/documentos/{$documento->id_documento}/versiones", [
            'archivo' => $this->pdf('Resolución corregida'),
            'motivo_cambio' => 'Corrección del número y firma',
        ])->assertSessionHasNoErrors();

        $respuestas = $documento->observaciones()->pluck('observacion.id_observacion')
            ->mapWithKeys(fn ($id) => [$id => 'Corregido en la versión 2.'])->all();

        $this->actingAs($this->encargado)->post($this->url($documento, 'reenviar'), ['respuestas' => $respuestas])
            ->assertSessionHas('exito');
        $this->assertSame(EstadoDocumento::PENDIENTE_REVISION, $this->estado($documento));
        $this->assertSame(0, $documento->observaciones()->pendientes()->count());

        $observacion = Observacion::first();
        $this->assertSame($this->encargado->id_usuario, $observacion->id_usuario_atiende);
        $this->assertSame(1, $observacion->version->numero_version); // se hizo sobre la versión 1

        // 4. El revisor aprueba y publica.
        $this->actingAs($this->revisor)->post($this->url($documento, 'aprobar'))->assertSessionHas('exito');
        $this->assertSame(EstadoDocumento::APROBADO, $this->estado($documento));
        $this->assertSame(0, Documento::publicados()->count());

        $this->actingAs($this->revisor)->post($this->url($documento, 'publicar'))->assertSessionHas('exito');
        $this->assertSame(EstadoDocumento::PUBLICADO, $this->estado($documento));
        $this->assertNotNull($documento->fecha_publicacion);
        $this->assertSame(1, Documento::publicados()->count());

        // Historial completo, en orden (sección 2.4).
        $this->assertSame([
            SeguimientoDocumento::ACCION_REGISTRO,
            SeguimientoDocumento::ACCION_ENVIO_REVISION,
            SeguimientoDocumento::ACCION_DEVOLUCION,
            SeguimientoDocumento::ACCION_NUEVA_VERSION,
            SeguimientoDocumento::ACCION_ENVIO_REVISION,
            SeguimientoDocumento::ACCION_APROBACION,
            SeguimientoDocumento::ACCION_PUBLICACION,
        ], $documento->seguimientos()->pluck('accion')->all());

        $this->assertSame(1, Auditoria::where('accion', Auditoria::PUBLICAR)->where('id_registro', $documento->id_documento)->count());
        Event::assertDispatchedTimes(DocumentoCambioDeEstado::class, 5);
        Event::assertDispatched(DocumentoCambioDeEstado::class, fn ($e) => $e->estadoNuevo === EstadoDocumento::PUBLICADO);

        $this->actingAs($this->encargado)->get("/panel/documentos/{$documento->id_documento}")
            ->assertOk()
            ->assertSee('Publicación')
            ->assertSee('Corregido en la versión 2.');
    }

    // ------------------------------------------------------------ Transiciones no permitidas

    public function test_solo_se_permiten_las_transiciones_definidas(): void
    {
        $documento = $this->documento(); // Borrador

        // Desde Borrador no se puede aprobar, publicar, devolver ni reenviar.
        $this->actingAs($this->revisor)->post($this->url($documento, 'aprobar'))->assertForbidden();
        $this->actingAs($this->revisor)->post($this->url($documento, 'publicar'))->assertForbidden();
        $this->actingAs($this->revisor)->post($this->url($documento, 'devolver'), ['observaciones' => ['x']])->assertForbidden();
        $this->actingAs($this->encargado)->post($this->url($documento, 'reenviar'))->assertForbidden();

        $this->actingAs($this->encargado)->post($this->url($documento, 'enviar-revision'));

        // Pendiente: no se puede publicar directamente ni volver a enviar.
        $this->actingAs($this->revisor)->post($this->url($documento, 'publicar'))->assertForbidden();
        $this->actingAs($this->encargado)->post($this->url($documento, 'enviar-revision'))->assertForbidden();

        $this->actingAs($this->revisor)->post($this->url($documento, 'aprobar'));
        $this->actingAs($this->revisor)->post($this->url($documento, 'publicar'));

        // Publicado: ya no admite ninguna acción.
        foreach (['enviar-revision', 'aprobar', 'publicar', 'reenviar'] as $accion) {
            $this->actingAs($this->revisor)->post($this->url($documento, $accion))->assertForbidden();
        }
        $this->assertSame(EstadoDocumento::PUBLICADO, $this->estado($documento));
    }

    public function test_cada_rol_solo_realiza_sus_acciones(): void
    {
        $documento = $this->documento();

        $this->actingAs($this->revisor)->post($this->url($documento, 'enviar-revision'))->assertForbidden();
        $this->actingAs($this->encargado)->post($this->url($documento, 'enviar-revision'));

        $this->actingAs($this->encargado)->post($this->url($documento, 'aprobar'))->assertForbidden();
        $this->actingAs($this->encargado)->post($this->url($documento, 'devolver'), ['observaciones' => ['x']])->assertForbidden();
        $this->actingAs($this->encargado)->get('/panel/revision')->assertForbidden();

        $this->assertSame(EstadoDocumento::PENDIENTE_REVISION, $this->estado($documento));
    }

    public function test_nadie_revisa_aprueba_ni_publica_lo_que_registro(): void
    {
        // Usuario con ambos roles (el administrador podría asignarlos).
        $ambos = Usuario::factory()->conRol(Rol::ENCARGADO)->conRol(Rol::REVISOR)->create();
        $documento = $this->documento($ambos);

        $this->actingAs($ambos)->post($this->url($documento, 'enviar-revision'))->assertSessionHas('exito');

        $this->actingAs($ambos)->post($this->url($documento, 'aprobar'))->assertForbidden();
        $this->actingAs($ambos)->post($this->url($documento, 'devolver'), ['observaciones' => ['x']])->assertForbidden();
        $this->actingAs($ambos)->get('/panel/revision')->assertOk()->assertDontSee($documento->numero);
        $this->actingAs($ambos)->get("/panel/documentos/{$documento->id_documento}")->assertSee('Usted registró este documento');

        // Otro revisor sí puede.
        $this->actingAs($this->revisor)->post($this->url($documento, 'aprobar'))->assertSessionHas('exito');
        $this->actingAs($ambos)->post($this->url($documento, 'publicar'))->assertForbidden();
    }

    public function test_el_servicio_rechaza_acciones_sobre_un_estado_que_ya_cambio(): void
    {
        $flujo = app(FlujoDocumental::class);
        $documento = $this->documento();
        $flujo->enviarARevision($documento, $this->encargado);

        // Dos revisores abren el mismo documento; el primero lo aprueba.
        $otroRevisor = Usuario::factory()->conRol(Rol::REVISOR)->create();
        $copiaDesactualizada = Documento::find($documento->id_documento);
        $flujo->aprobar($documento, $this->revisor);

        try {
            $flujo->devolver($copiaDesactualizada, $otroRevisor, ['Observación tardía']);
            $this->fail('Se esperaba TransicionNoPermitida.');
        } catch (TransicionNoPermitida $e) {
            $this->assertStringContainsString('Aprobado para publicación', $e->getMessage());
        }

        // El propio registrador tampoco puede aprobar aunque llame al servicio directamente.
        $propio = $this->documento();
        $flujo->enviarARevision($propio, $this->encargado);
        $this->expectException(TransicionNoPermitida::class);
        $flujo->aprobar($propio, $this->encargado);
    }

    // ------------------------------------------------------------ Observaciones

    public function test_devolver_exige_al_menos_una_observacion(): void
    {
        $documento = $this->documento();
        $this->actingAs($this->encargado)->post($this->url($documento, 'enviar-revision'));

        $this->actingAs($this->revisor)->post($this->url($documento, 'devolver'), ['observaciones' => ['', '   ']])
            ->assertSessionHasErrors('observaciones');

        $this->assertSame(EstadoDocumento::PENDIENTE_REVISION, $this->estado($documento));
        $this->assertSame(0, Observacion::count());
    }

    public function test_reenviar_exige_responder_todas_las_observaciones(): void
    {
        $documento = $this->documento();
        $this->actingAs($this->encargado)->post($this->url($documento, 'enviar-revision'));
        $this->actingAs($this->revisor)->post($this->url($documento, 'devolver'), ['observaciones' => ['Primera', 'Segunda']]);

        [$primera, $segunda] = $documento->observaciones()->orderBy('observacion.id_observacion')->pluck('observacion.id_observacion')->all();

        $this->actingAs($this->encargado)->post($this->url($documento, 'reenviar'), ['respuestas' => [$primera => 'Atendida']])
            ->assertSessionHasErrors("respuestas.{$segunda}");

        $this->assertSame(EstadoDocumento::OBSERVADO, $this->estado($documento));
        $this->assertSame(2, $documento->observaciones()->pendientes()->count()); // no se guardó nada a medias
    }

    // ------------------------------------------------------------ Bandeja del revisor

    public function test_la_bandeja_muestra_los_documentos_por_estado(): void
    {
        $pendiente = $this->documento();
        $aprobado = $this->documento();
        $borrador = $this->documento();

        $this->actingAs($this->encargado)->post($this->url($pendiente, 'enviar-revision'));
        $this->actingAs($this->encargado)->post($this->url($aprobado, 'enviar-revision'));
        $this->actingAs($this->revisor)->post($this->url($aprobado, 'aprobar'));
        $this->flushSession();

        // Se comparan los enlaces de la bandeja (la campana de notificaciones también menciona números).
        $enlace = fn (Documento $d) => route('panel.documentos.show', $d).'"';

        $this->actingAs($this->revisor)->get('/panel/revision')
            ->assertOk()->assertSee($enlace($pendiente), false)->assertDontSee($enlace($aprobado), false)->assertDontSee($enlace($borrador), false);

        $this->actingAs($this->revisor)->get('/panel/revision?bandeja=aprobados')
            ->assertSee($enlace($aprobado), false)->assertDontSee($enlace($pendiente), false);
    }

    public function test_el_seeder_de_demostracion_recorre_el_flujo_real(): void
    {
        $this->seed(DemoSeeder::class);

        $conteo = fn (string $estado) => Documento::enEstado($estado)->count();

        $this->assertSame(3, $conteo(EstadoDocumento::BORRADOR));
        $this->assertSame(3, $conteo(EstadoDocumento::PENDIENTE_REVISION));
        $this->assertSame(2, $conteo(EstadoDocumento::OBSERVADO));
        $this->assertSame(2, $conteo(EstadoDocumento::APROBADO));
        $this->assertSame(10, $conteo(EstadoDocumento::PUBLICADO));
        $this->assertSame(4, Observacion::whereNull('fecha_atencion')->count()); // 2 documentos observados × 2
        $this->assertGreaterThan(0, Observacion::whereNotNull('fecha_atencion')->count());
    }
}
