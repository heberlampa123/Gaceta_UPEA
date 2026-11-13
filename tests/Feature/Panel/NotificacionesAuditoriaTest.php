<?php

namespace Tests\Feature\Panel;

use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\Notificacion;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\FlujoDocumental;
use App\Services\GestionDocumental;
use App\Support\PdfSimple;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificacionesAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Usuario $encargado;

    private Usuario $revisor;

    private Usuario $otroRevisor;

    private FlujoDocumental $flujo;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->encargado = Usuario::factory()->conRol(Rol::ENCARGADO)->create();
        $this->revisor = Usuario::factory()->conRol(Rol::REVISOR)->create();
        $this->otroRevisor = Usuario::factory()->conRol(Rol::REVISOR)->create();
        $this->flujo = app(FlujoDocumental::class);
    }

    private function documento(): Documento
    {
        $datos = Documento::factory()->make()->only(['id_tipo_documental', 'numero', 'gestion', 'titulo', 'instancia_emisora']);
        $datos['fecha_emision'] = '2026-11-01';

        return app(GestionDocumental::class)->registrar($datos, UploadedFile::fake()->createWithContent('r.pdf', PdfSimple::generar($datos['numero'])), $this->encargado);
    }

    private function tipos(Usuario $usuario): array
    {
        return $usuario->notificaciones()->orderBy('id_notificacion')->pluck('tipo')->all();
    }

    // ------------------------------------------------------------ Generación de notificaciones

    public function test_cada_paso_del_flujo_notifica_a_quien_corresponde(): void
    {
        $documento = $this->documento();

        $this->flujo->enviarARevision($documento, $this->encargado);
        // Ambos revisores reciben el aviso; el encargado (quien actuó) no.
        $this->assertSame([Notificacion::NUEVO_PENDIENTE], $this->tipos($this->revisor));
        $this->assertSame([Notificacion::NUEVO_PENDIENTE], $this->tipos($this->otroRevisor));
        $this->assertSame([], $this->tipos($this->encargado));

        $this->flujo->devolver($documento, $this->revisor, ['Falta la firma.']);
        $this->assertSame([Notificacion::OBSERVADO], $this->tipos($this->encargado));

        $respuestas = $documento->observaciones()->pluck('observacion.id_observacion')->mapWithKeys(fn ($id) => [$id => 'Corregido'])->all();
        $this->flujo->reenviar($documento, $this->encargado, $respuestas);
        $ultima = $this->revisor->notificaciones()->latest('id_notificacion')->first();
        $this->assertSame('Documento corregido por revisar', $ultima->titulo);

        $this->flujo->aprobar($documento, $this->revisor);
        $this->flujo->publicar($documento, $this->revisor);

        $this->assertSame([Notificacion::OBSERVADO, Notificacion::APROBADO, Notificacion::PUBLICADO], $this->tipos($this->encargado));
        // El revisor que aprobó y publicó no se notifica a sí mismo.
        $this->assertSame([Notificacion::NUEVO_PENDIENTE, Notificacion::NUEVO_PENDIENTE], $this->tipos($this->revisor));
        $this->assertSame($documento->id_documento, Notificacion::where('tipo', Notificacion::PUBLICADO)->value('id_documento'));
    }

    public function test_no_se_notifica_a_revisores_inactivos_ni_al_registrador_revisor(): void
    {
        $this->otroRevisor->forceFill(['activo' => false])->save();
        $ambos = Usuario::factory()->conRol(Rol::ENCARGADO)->conRol(Rol::REVISOR)->create();

        $datos = Documento::factory()->make()->only(['id_tipo_documental', 'numero', 'gestion', 'titulo', 'instancia_emisora']);
        $datos['fecha_emision'] = '2026-11-01';
        $documento = app(GestionDocumental::class)->registrar($datos, UploadedFile::fake()->createWithContent('r.pdf', PdfSimple::generar('x')), $ambos);

        $this->flujo->enviarARevision($documento, $ambos);

        $this->assertSame(1, $this->revisor->notificaciones()->count());
        $this->assertSame(0, $this->otroRevisor->notificaciones()->count());
        $this->assertSame(0, $ambos->notificaciones()->count());
    }

    // ------------------------------------------------------------ API de la campana (Axios + Sanctum)

    public function test_la_api_de_notificaciones_requiere_sesion_y_solo_muestra_las_propias(): void
    {
        $this->getJson('/api/notificaciones/resumen')->assertUnauthorized();

        $this->flujo->enviarARevision($this->documento(), $this->encargado);

        $this->actingAs($this->revisor)->getJson('/api/notificaciones/resumen')
            ->assertOk()
            ->assertJsonPath('no_leidas', 1)
            ->assertJsonCount(1, 'recientes')
            ->assertJsonPath('recientes.0.tipo', Notificacion::NUEVO_PENDIENTE)
            ->assertJsonPath('recientes.0.leida', false)
            ->assertJsonStructure(['recientes' => [['id', 'icono', 'titulo', 'mensaje', 'fecha_texto', 'url']]]);

        $this->actingAs($this->encargado)->getJson('/api/notificaciones/resumen')->assertJsonPath('no_leidas', 0);

        // No puede marcar como leída una notificación ajena.
        $ajena = $this->revisor->notificaciones()->first();
        $this->actingAs($this->encargado)->patchJson("/api/notificaciones/{$ajena->id_notificacion}/leer")->assertNotFound();
        $this->assertNull($ajena->refresh()->fecha_lectura);
    }

    public function test_marcar_como_leidas_desde_la_api_y_al_abrir(): void
    {
        $this->flujo->enviarARevision($this->documento(), $this->encargado);
        $this->flujo->enviarARevision($primero = $this->documento(), $this->encargado);
        $notificacion = $this->revisor->notificaciones()->where('id_documento', $primero->id_documento)->first();

        $this->actingAs($this->revisor)->patchJson("/api/notificaciones/{$notificacion->id_notificacion}/leer")
            ->assertOk()->assertJsonPath('no_leidas', 1);

        $this->actingAs($this->revisor)->postJson('/api/notificaciones/leer-todas')->assertJsonPath('no_leidas', 0);

        // Abrir desde la campana: marca como leída y lleva al documento.
        $this->flujo->enviarARevision($tercero = $this->documento(), $this->encargado);
        $nueva = $this->revisor->notificaciones()->where('id_documento', $tercero->id_documento)->first();

        $this->actingAs($this->revisor)->get("/panel/notificaciones/{$nueva->id_notificacion}/abrir")
            ->assertRedirect("/panel/documentos/{$tercero->id_documento}");
        $this->assertNotNull($nueva->refresh()->fecha_lectura);

        $this->actingAs($this->encargado)->get("/panel/notificaciones/{$nueva->id_notificacion}/abrir")->assertNotFound();
    }

    public function test_la_campana_y_la_pagina_de_notificaciones_se_muestran(): void
    {
        $this->flujo->enviarARevision($this->documento(), $this->encargado);

        $this->actingAs($this->revisor)->get('/panel')
            ->assertOk()
            ->assertSee('data-notificaciones', false)
            ->assertSee('Nuevo documento por revisar')
            ->assertSee('Documentos por revisar');

        $this->actingAs($this->revisor)->get('/panel/notificaciones')->assertOk()->assertSee('1 sin leer');
        $this->actingAs($this->revisor)->get('/panel/notificaciones?no_leidas=1')->assertOk()->assertSee('Nuevo documento por revisar');
    }

    // ------------------------------------------------------------ Auditoría

    public function test_la_auditoria_solo_la_consulta_quien_tiene_el_permiso_y_se_puede_filtrar(): void
    {
        $admin = Usuario::factory()->conRol(Rol::ADMIN)->create();
        $documento = $this->documento();
        $this->flujo->enviarARevision($documento, $this->encargado);

        $this->actingAs($this->encargado)->get('/panel/auditoria')->assertForbidden();

        $this->actingAs($admin)->get('/panel/auditoria')->assertOk()->assertSee('Envío a revisión');

        $this->actingAs($admin)->get('/panel/auditoria?accion='.Auditoria::ENVIAR_REVISION)
            ->assertSee('Envío a revisión')
            ->assertSee('1 registro(s)');

        $this->actingAs($admin)->get('/panel/auditoria?usuario='.$this->revisor->id_usuario)
            ->assertSee('0 registro(s)');

        $this->actingAs($admin)->get("/panel/auditoria?entidad=Documento&registro={$documento->id_documento}")
            ->assertSee('Creación')
            ->assertSee('Envío a revisión');
    }

    public function test_la_auditoria_se_exporta_en_csv(): void
    {
        $admin = Usuario::factory()->conRol(Rol::ADMIN)->create();
        $this->flujo->enviarARevision($this->documento(), $this->encargado);

        $respuesta = $this->actingAs($admin)->get('/panel/auditoria/exportar?accion='.Auditoria::ENVIAR_REVISION);
        $respuesta->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $respuesta->streamedContent();
        $this->assertStringContainsString('Fecha;Usuario;Acción;Entidad', $csv);
        $this->assertStringContainsString('Envío a revisión', $csv);
        $this->assertSame(2, substr_count(trim($csv), "\n") + 1); // encabezado + 1 registro
    }

    // ------------------------------------------------------------ Tablero

    public function test_el_tablero_muestra_los_pendientes_de_cada_rol(): void
    {
        $observado = $this->documento();
        $this->flujo->enviarARevision($observado, $this->encargado);
        $this->flujo->devolver($observado, $this->revisor, ['Corregir']);
        $this->documento(); // borrador
        $this->flujo->enviarARevision($this->documento(), $this->encargado); // pendiente

        $this->actingAs($this->encargado)->get('/panel')
            ->assertOk()
            ->assertSee('Mis documentos observados')
            ->assertSee('Mis borradores sin enviar')
            ->assertDontSee('Documentos por revisar')
            ->assertDontSee('Visibles en el portal público') // indicadores generales: solo el Administrador
            ->assertSee('Su documento fue observado');

        $this->actingAs($this->revisor)->get('/panel')
            ->assertSee('Documentos por revisar')
            ->assertSee('Aprobados por publicar')
            ->assertDontSee('Visibles en el portal público')
            ->assertDontSee('Mis borradores sin enviar');

        $admin = Usuario::factory()->conRol(Rol::ADMIN)->create();
        $this->actingAs($admin)->get('/panel')
            ->assertSee('Cuentas bloqueadas')
            ->assertSee('Visibles en el portal público')
            ->assertSee('Ver publicados');
    }
}
