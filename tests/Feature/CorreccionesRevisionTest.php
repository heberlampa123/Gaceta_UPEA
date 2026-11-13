<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\EstadoDocumento;
use App\Models\Rol;
use App\Models\SeguimientoDocumento;
use App\Models\TipoDocumental;
use App\Models\Usuario;
use App\Services\FlujoDocumental;
use App\Services\GestionDocumental;
use App\Support\PdfSimple;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pruebas de regresión de las 10 brechas corregidas tras la revisión del 13/11/2026.
 */
class CorreccionesRevisionTest extends TestCase
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

    private function pdf(string $texto = 'x'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('r.pdf', PdfSimple::generar($texto));
    }

    private function datos(array $cambios = []): array
    {
        return [
            'id_tipo_documental' => TipoDocumental::where('codigo', TipoDocumental::RES_HCU)->value('id_tipo_documental'),
            'numero' => '025/2026',
            'gestion' => 2026,
            'fecha_emision' => '2026-11-01',
            'titulo' => 'Resolución de prueba',
            'instancia_emisora' => 'Honorable Consejo Universitario',
            'archivo' => $this->pdf(),
            ...$cambios,
        ];
    }

    private function registrar(array $cambios = []): Documento
    {
        return app(GestionDocumental::class)->registrar(collect($this->datos($cambios))->except('archivo')->all(), $this->pdf(), $this->encargado);
    }

    // 1 ------------------------------------------------------------------------------------------

    public function test_1_no_se_envia_ni_aprueba_sin_los_campos_obligatorios_del_tipo(): void
    {
        $documento = $this->registrar();
        $documento->tipoDocumental->update(['campos_adicionales' => [
            ['nombre' => 'sesion', 'etiqueta' => 'N.º de sesión', 'tipo' => 'numero', 'obligatorio' => true, 'opciones' => []],
        ]]);

        $this->actingAs($this->encargado)->post("/panel/documentos/{$documento->id_documento}/enviar-revision")
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Faltan datos obligatorios') && str_contains($m, 'N.º de sesión'));
        $this->assertSame(EstadoDocumento::BORRADOR, $documento->refresh()->estadoActual->codigo);

        // Al completarlo, sí avanza.
        $documento->update(['metadatos' => ['sesion' => 12]]);
        $this->actingAs($this->encargado)->post("/panel/documentos/{$documento->id_documento}/enviar-revision")->assertSessionHas('exito');

        // Si el campo se vacía antes de aprobar, el revisor no puede aprobar (debe devolver).
        $documento->update(['metadatos' => ['sesion' => null]]);
        $this->actingAs($this->revisor)->post("/panel/documentos/{$documento->id_documento}/aprobar")
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Devuelva el documento'));
        $this->actingAs($this->revisor)->post("/panel/documentos/{$documento->id_documento}/devolver", ['observaciones' => ['Falta el N.º de sesión']])
            ->assertSessionHas('exito');
    }

    // 2 ------------------------------------------------------------------------------------------

    public function test_2_un_borrador_se_anula_con_motivo_y_libera_su_numero(): void
    {
        $documento = $this->registrar(['numero' => '050/2026']);

        $this->actingAs($this->encargado)->post("/panel/documentos/{$documento->id_documento}/anular", ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->actingAs($this->encargado)->post("/panel/documentos/{$documento->id_documento}/anular", ['motivo' => 'Número duplicado por error'])
            ->assertRedirect('/panel/documentos');
        $this->flushSession(); // descarta el mensaje flash (menciona el número)

        $documento->refresh();
        $this->assertFalse($documento->activo);
        $this->assertDatabaseHas('seguimiento_documento', ['id_documento' => $documento->id_documento, 'accion' => SeguimientoDocumento::ACCION_ANULACION]);
        $this->assertDatabaseHas('auditoria', ['accion' => Auditoria::DESACTIVAR, 'entidad' => 'Documento', 'id_registro' => $documento->id_documento]);

        // Ya no aparece en el listado, su ficha lo indica y no admite acciones.
        $this->actingAs($this->encargado)->get('/panel/documentos')->assertDontSee('050/2026');
        $this->actingAs($this->encargado)->get("/panel/documentos/{$documento->id_documento}")->assertSee('Este borrador fue anulado');
        $this->actingAs($this->encargado)->post("/panel/documentos/{$documento->id_documento}/enviar-revision")
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'anulado'));

        // El número vuelve a estar disponible.
        $this->actingAs($this->encargado)->post('/panel/documentos', $this->datos(['numero' => '050/2026']))->assertSessionHasNoErrors();
        $this->assertSame(1, Documento::activos()->where('numero', '050/2026')->count());
    }

    public function test_2_solo_se_anulan_borradores(): void
    {
        $documento = $this->registrar();
        app(FlujoDocumental::class)->enviarARevision($documento, $this->encargado);

        $this->actingAs($this->encargado)->post("/panel/documentos/{$documento->id_documento}/anular", ['motivo' => 'Ya no corresponde'])
            ->assertSessionHas('error');
        $this->assertTrue($documento->refresh()->activo);

        $this->actingAs($this->revisor)->post("/panel/documentos/{$documento->id_documento}/anular", ['motivo' => 'Sin permiso'])
            ->assertForbidden();
    }

    // 3 ------------------------------------------------------------------------------------------

    public function test_3_un_borrador_de_tipo_desactivado_se_puede_editar(): void
    {
        $documento = $this->registrar();
        $documento->tipoDocumental->update(['activo' => false]);

        $this->actingAs($this->encargado)->put("/panel/documentos/{$documento->id_documento}", collect($this->datos(['titulo' => 'Título corregido']))->except('archivo')->all())
            ->assertSessionHasNoErrors();
        $this->assertSame('Título corregido', $documento->refresh()->titulo);

        // Pero no se puede registrar un documento nuevo con ese tipo.
        $this->actingAs($this->encargado)->post('/panel/documentos', $this->datos(['numero' => '026/2026']))->assertSessionHasErrors('id_tipo_documental');
    }

    // 4 y 5 --------------------------------------------------------------------------------------

    public function test_4_el_nombre_de_usuario_no_distingue_mayusculas(): void
    {
        $usuario = Usuario::factory()->create(['nombre_usuario' => 'jperez', 'contrasena_hash' => 'Clave2026']);

        $this->post('/login', ['usuario' => 'JPerez', 'contrasena' => 'Clave2026'])->assertRedirect('/panel');
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_5_el_bloqueo_no_revela_si_la_cuenta_existe(): void
    {
        Usuario::factory()->create(['nombre_usuario' => 'jperez']);

        $mensajeTras5 = function (string $usuario) {
            for ($i = 1; $i < Usuario::MAX_INTENTOS_FALLIDOS; $i++) {
                $this->post('/login', ['usuario' => $usuario, 'contrasena' => 'mala']);
            }
            $respuesta = $this->post('/login', ['usuario' => $usuario, 'contrasena' => 'mala']);

            $errores = $respuesta->baseResponse->getSession()->get('errors');

            return is_array($errores) ? $errores['default']['messages']['usuario'][0] : $errores->getBag('default')->first('usuario');
        };

        $existente = $mensajeTras5('jperez');
        $inexistente = $mensajeTras5('noexiste');

        $this->assertStringContainsString('bloqueada temporalmente', $existente);
        $this->assertSame($existente, $inexistente);

        // El nombre inexistente también queda «bloqueado» (mismo comportamiento) y se libera solo.
        // (Se avanza un minuto: ya se usaron los 10 intentos por minuto permitidos por IP.)
        $this->travel(61)->seconds();
        $this->post('/login', ['usuario' => 'noexiste', 'contrasena' => 'mala'])
            ->assertSessionHasErrors(['usuario' => $existente]);
        $this->travel(Usuario::MINUTOS_BLOQUEO + 1)->minutes();
        $this->post('/login', ['usuario' => 'noexiste', 'contrasena' => 'mala'])
            ->assertSessionHasErrors(['usuario' => 'El usuario o la contraseña son incorrectos.']);
    }

    // 6 ------------------------------------------------------------------------------------------

    public function test_6_al_cambiar_la_contrasena_se_cierran_las_demas_sesiones(): void
    {
        $usuario = Usuario::factory()->conRol(Rol::ENCARGADO)->create(['contrasena_hash' => 'Actual2026']);

        // Sesión abierta en «otro equipo».
        $this->actingAs($usuario)->get('/panel')->assertOk();

        // La contraseña cambia (desde otra sesión o porque el administrador la restableció).
        Usuario::whereKey($usuario->id_usuario)->update(['contrasena_hash' => bcrypt('Nueva2026')]);

        // En el sistema real el usuario se vuelve a leer de la base en cada petición.
        $this->app['auth']->guard('web')->setUser($usuario->fresh());

        $this->get('/panel')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_6_quien_cambia_su_propia_contrasena_sigue_conectado(): void
    {
        $usuario = Usuario::factory()->create(['contrasena_hash' => 'Actual2026']);

        $this->actingAs($usuario)->get('/panel')->assertOk();
        $this->put('/panel/perfil/contrasena', [
            'contrasena_actual' => 'Actual2026',
            'contrasena' => 'Nueva2026x',
            'contrasena_confirmation' => 'Nueva2026x',
        ])->assertRedirect('/panel/perfil');

        $this->get('/panel')->assertOk();
        $this->assertAuthenticatedAs($usuario);
    }

    // 7 ------------------------------------------------------------------------------------------

    public function test_7_las_paginas_html_tienen_politica_de_seguridad_de_contenido(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);

        // No se aplica a los PDF ni a la API JSON.
        $this->assertFalse($this->getJson('/api/documentos')->headers->has('Content-Security-Policy'));
    }

    // 8 y 9 --------------------------------------------------------------------------------------

    public function test_8_la_fecha_de_emision_debe_corresponder_a_la_gestion(): void
    {
        $this->actingAs($this->encargado)->post('/panel/documentos', $this->datos(['numero' => '001', 'gestion' => 2019, 'fecha_emision' => '2026-11-01']))
            ->assertSessionHasErrors(['fecha_emision' => 'La fecha de emisión (2026) no corresponde a la gestión 2019.']);
    }

    public function test_9_el_anio_del_numero_debe_coincidir_con_la_gestion(): void
    {
        $this->actingAs($this->encargado)->post('/panel/documentos', $this->datos(['numero' => '025/2026', 'gestion' => 2025, 'fecha_emision' => '2025-11-01']))
            ->assertSessionHasErrors(['numero' => 'El número indica el año 2026, pero la gestión es 2025.']);

        // Sin año en el número, o con el año correcto: se acepta.
        $this->actingAs($this->encargado)->post('/panel/documentos', $this->datos(['numero' => '025-A', 'gestion' => 2025, 'fecha_emision' => '2025-11-01']))
            ->assertSessionHasNoErrors();
    }

    // 10 -----------------------------------------------------------------------------------------

    public function test_10_el_doble_clic_muestra_un_aviso_y_no_un_error_403(): void
    {
        $documento = $this->registrar();
        $url = "/panel/documentos/{$documento->id_documento}/enviar-revision";
        $ficha = "/panel/documentos/{$documento->id_documento}";

        $this->actingAs($this->encargado)->from($ficha)->post($url)->assertSessionHas('exito');
        $this->actingAs($this->encargado)->from($ficha)->post($url)
            ->assertRedirect($ficha)
            ->assertSessionHas('error', 'Esta acción ya no está disponible: el documento está en estado «Pendiente de revisión».');
    }
}
