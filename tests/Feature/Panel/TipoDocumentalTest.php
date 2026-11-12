<?php

namespace Tests\Feature\Panel;

use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\Rol;
use App\Models\TipoDocumental;
use App\Models\Usuario;
use App\Support\PdfSimple;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TipoDocumentalTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = Usuario::factory()->conRol(Rol::ADMIN)->create();
    }

    /** Tipo «Resolución Rectoral» con un campo de cada clase. */
    private function datosTipo(array $cambios = []): array
    {
        return [
            'codigo' => 'res_rectoral',
            'nombre' => 'Resolución Rectoral',
            'descripcion' => 'Resoluciones emitidas por el Rectorado.',
            'activo' => '1',
            'campos' => [
                ['etiqueta' => 'N.º de sesión', 'nombre' => '', 'tipo' => 'numero', 'obligatorio' => '1'],
                ['etiqueta' => 'Tipo de sesión', 'nombre' => 'tipo_sesion', 'tipo' => 'seleccion', 'obligatorio' => '1', 'opciones' => "Ordinaria\nExtraordinaria"],
                ['etiqueta' => 'Fecha de sesión', 'nombre' => 'fecha_sesion', 'tipo' => 'fecha', 'obligatorio' => '0'],
                ['etiqueta' => 'Requiere difusión', 'nombre' => 'requiere_difusion', 'tipo' => 'si_no', 'obligatorio' => '0'],
                ['etiqueta' => '', 'nombre' => '', 'tipo' => 'texto'], // fila vacía: se ignora
            ],
            ...$cambios,
        ];
    }

    private function crearTipo(array $cambios = []): TipoDocumental
    {
        $this->actingAs($this->admin)->post('/panel/tipos-documentales', $this->datosTipo($cambios))->assertRedirect('/panel/tipos-documentales');
        $this->flushSession(); // descarta el mensaje flash para no afectar la siguiente petición

        return TipoDocumental::where('codigo', 'RES_RECTORAL')->firstOrFail();
    }

    // ------------------------------------------------------------ Administración de tipos

    public function test_solo_quien_tiene_el_permiso_administra_tipos(): void
    {
        $encargado = Usuario::factory()->conRol(Rol::ENCARGADO)->create();

        $this->actingAs($encargado)->get('/panel/tipos-documentales')->assertForbidden();
        $this->actingAs($encargado)->post('/panel/tipos-documentales', $this->datosTipo())->assertForbidden();

        $this->actingAs($this->admin)->get('/panel/tipos-documentales')->assertOk()->assertSee('Resolución del HCU');
        $this->actingAs($this->admin)->get('/panel/tipos-documentales/create')->assertOk()->assertSee('Agregar campo');
    }

    public function test_se_crea_un_tipo_nuevo_con_sus_campos_sin_modificar_la_base_de_datos(): void
    {
        $tipo = $this->crearTipo();

        $this->assertTrue($tipo->activo);
        $this->assertCount(4, $tipo->campos());
        $this->assertSame(['no_de_sesion', 'tipo_sesion', 'fecha_sesion', 'requiere_difusion'], array_column($tipo->campos(), 'nombre'));
        $this->assertSame(['Ordinaria', 'Extraordinaria'], $tipo->campos()[1]['opciones']);
        $this->assertSame([], $tipo->campos()[0]['opciones']);
        $this->assertTrue($tipo->campos()[0]['obligatorio']);

        $this->assertDatabaseHas('auditoria', ['accion' => Auditoria::CREAR, 'entidad' => 'TipoDocumental', 'id_registro' => $tipo->id_tipo_documental]);
    }

    public function test_la_validacion_de_campos_detecta_errores(): void
    {
        $this->actingAs($this->admin)->post('/panel/tipos-documentales', $this->datosTipo([
            'codigo' => 'RES_HCU', // ya existe
            'campos' => [
                ['etiqueta' => 'Sesión', 'nombre' => 'sesion', 'tipo' => 'numero'],
                ['etiqueta' => 'Sesión 2', 'nombre' => 'sesion', 'tipo' => 'texto'], // nombre repetido
                ['etiqueta' => 'Modalidad', 'nombre' => 'modalidad', 'tipo' => 'seleccion', 'opciones' => 'Única'], // una sola opción
                ['etiqueta' => 'Otro', 'nombre' => '1campo', 'tipo' => 'desconocido'],
            ],
        ]))->assertSessionHasErrors(['codigo', 'campos.1.nombre', 'campos.2.opciones', 'campos.3.nombre', 'campos.3.tipo']);

        $this->assertSame(1, TipoDocumental::count());
    }

    public function test_al_editar_el_codigo_no_cambia_y_se_audita(): void
    {
        $tipo = $this->crearTipo();

        $this->actingAs($this->admin)->put("/panel/tipos-documentales/{$tipo->id_tipo_documental}", [
            'codigo' => 'OTRO',
            'nombre' => 'Resolución Rectoral',
        ])->assertSessionHasErrors('codigo');

        $this->actingAs($this->admin)->put("/panel/tipos-documentales/{$tipo->id_tipo_documental}", [
            'nombre' => 'Resolución del Rectorado',
            'activo' => '0',
            'campos' => [['etiqueta' => 'N.º de sesión', 'nombre' => 'no_de_sesion', 'tipo' => 'numero', 'obligatorio' => '0']],
        ])->assertRedirect('/panel/tipos-documentales');

        $tipo->refresh();
        $this->assertSame('RES_RECTORAL', $tipo->codigo);
        $this->assertFalse($tipo->activo);
        $this->assertCount(1, $tipo->campos());
        $this->assertDatabaseHas('auditoria', ['accion' => Auditoria::MODIFICAR, 'entidad' => 'TipoDocumental', 'id_registro' => $tipo->id_tipo_documental]);
    }

    // ------------------------------------------------------------ Uso en los documentos

    public function test_el_documento_pide_y_guarda_los_campos_del_tipo(): void
    {
        $tipo = $this->crearTipo();
        $encargado = Usuario::factory()->conRol(Rol::ENCARGADO)->create();

        $datos = [
            'id_tipo_documental' => $tipo->id_tipo_documental,
            'numero' => '010/2026',
            'gestion' => 2026,
            'fecha_emision' => '2026-11-01',
            'titulo' => 'Resolución Rectoral de prueba',
            'instancia_emisora' => 'Rectorado',
            'archivo' => UploadedFile::fake()->createWithContent('rr.pdf', PdfSimple::generar('RR 010/2026')),
        ];

        // Faltan los campos obligatorios del tipo.
        $this->actingAs($encargado)->post('/panel/documentos', $datos)
            ->assertSessionHasErrors(['metadatos.no_de_sesion', 'metadatos.tipo_sesion']);

        // Opción que no está en la lista.
        $this->actingAs($encargado)->post('/panel/documentos', [...$datos, 'metadatos' => ['no_de_sesion' => '5', 'tipo_sesion' => 'Secreta']])
            ->assertSessionHasErrors('metadatos.tipo_sesion');

        $this->actingAs($encargado)->post('/panel/documentos', [...$datos, 'metadatos' => [
            'no_de_sesion' => '12',
            'tipo_sesion' => 'Extraordinaria',
            'fecha_sesion' => '2026-10-30',
            'requiere_difusion' => '1',
            'campo_inventado' => 'no debe guardarse',
        ]])->assertSessionHasNoErrors();

        $documento = Documento::firstOrFail();
        // JSONB no conserva el orden de las claves: se compara el contenido y los tipos de dato.
        $this->assertEquals([
            'no_de_sesion' => 12,
            'tipo_sesion' => 'Extraordinaria',
            'fecha_sesion' => '2026-10-30',
            'requiere_difusion' => true,
        ], $documento->metadatos);
        $this->assertIsInt($documento->metadatos['no_de_sesion']);
        $this->assertTrue($documento->metadatos['requiere_difusion']);

        $this->actingAs($encargado)->get("/panel/documentos/{$documento->id_documento}")
            ->assertOk()
            ->assertSee('N.º de sesión')
            ->assertSee('Extraordinaria')
            ->assertSee('30/10/2026');
    }

    public function test_un_tipo_inactivo_no_se_ofrece_para_nuevos_documentos(): void
    {
        $tipo = $this->crearTipo(['activo' => '0']);
        $encargado = Usuario::factory()->conRol(Rol::ENCARGADO)->create();

        $this->actingAs($encargado)->get('/panel/documentos/registrar')
            ->assertOk()
            ->assertDontSee('Resolución Rectoral');

        $this->actingAs($encargado)->post('/panel/documentos', [
            'id_tipo_documental' => $tipo->id_tipo_documental,
            'numero' => '011/2026',
            'gestion' => 2026,
            'fecha_emision' => '2026-11-01',
            'titulo' => 'No permitido',
            'instancia_emisora' => 'Rectorado',
            'archivo' => UploadedFile::fake()->createWithContent('rr.pdf', PdfSimple::generar('RR')),
        ])->assertSessionHasErrors('id_tipo_documental');
    }

    public function test_el_tipo_sin_campos_no_guarda_metadatos(): void
    {
        $encargado = Usuario::factory()->conRol(Rol::ENCARGADO)->create();

        $this->actingAs($encargado)->post('/panel/documentos', [
            'id_tipo_documental' => TipoDocumental::where('codigo', TipoDocumental::RES_HCU)->value('id_tipo_documental'),
            'numero' => '030/2026',
            'gestion' => 2026,
            'fecha_emision' => '2026-11-01',
            'titulo' => 'Resolución del HCU',
            'instancia_emisora' => 'Honorable Consejo Universitario',
            'metadatos' => ['algo' => 'x'],
            'archivo' => UploadedFile::fake()->createWithContent('hcu.pdf', PdfSimple::generar('HCU')),
        ])->assertSessionHasNoErrors();

        $this->assertNull(Documento::firstOrFail()->metadatos);
    }
}
