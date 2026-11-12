<?php

namespace Tests\Feature\Panel;

use App\Models\Auditoria;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioAdministracionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Usuario::factory()->conRol(Rol::ADMIN)->create();
    }

    private function idRol(string $codigo): int
    {
        return Rol::where('codigo', $codigo)->value('id_rol');
    }

    private function datosUsuario(array $cambios = []): array
    {
        return [
            'nombres' => 'María',
            'apellidos' => 'Mamani Quispe',
            'correo' => 'MMamani@upea.edu.bo',
            'nombre_usuario' => 'mmamani',
            'cargo' => 'Secretaria',
            'roles' => [$this->idRol(Rol::ENCARGADO)],
            ...$cambios,
        ];
    }

    public function test_solo_quien_tiene_el_permiso_puede_administrar_usuarios(): void
    {
        $encargado = Usuario::factory()->conRol(Rol::ENCARGADO)->create();

        $this->actingAs($encargado)->get('/panel/usuarios')->assertForbidden();
        $this->actingAs($encargado)->post('/panel/usuarios', $this->datosUsuario())->assertForbidden();

        $this->actingAs($this->admin)->get('/panel/usuarios')->assertOk()->assertSee($encargado->nombre_usuario);
    }

    public function test_el_administrador_crea_una_cuenta_con_contrasena_temporal(): void
    {
        $this->actingAs($this->admin)
            ->post('/panel/usuarios', $this->datosUsuario())
            ->assertRedirect('/panel/usuarios')
            ->assertSessionHas('contrasena_temporal');

        $usuario = Usuario::where('nombre_usuario', 'mmamani')->firstOrFail();
        $temporal = session('contrasena_temporal')['contrasena'];

        $this->assertSame('mmamani@upea.edu.bo', $usuario->correo);
        $this->assertTrue($usuario->debe_cambiar_contrasena);
        $this->assertTrue(Hash::check($temporal, $usuario->contrasena_hash));
        $this->assertTrue($usuario->tieneRol(Rol::ENCARGADO));

        $auditoria = Auditoria::where('accion', Auditoria::CREAR)->where('entidad', 'Usuario')->firstOrFail();
        $this->assertSame($this->admin->id_usuario, $auditoria->id_usuario);
        $this->assertSame(['ENCARGADO'], $auditoria->detalle['roles']);
        $this->assertArrayNotHasKey('contrasena_hash', $auditoria->detalle['nuevo']);
    }

    public function test_la_validacion_rechaza_datos_duplicados_o_invalidos(): void
    {
        Usuario::factory()->create(['nombre_usuario' => 'mmamani', 'correo' => 'mmamani@upea.edu.bo']);

        $this->actingAs($this->admin)
            ->post('/panel/usuarios', $this->datosUsuario(['roles' => [], 'contrasena' => 'corta']))
            ->assertSessionHasErrors(['correo', 'nombre_usuario', 'roles', 'contrasena']);
    }

    public function test_al_editar_se_auditan_los_valores_anteriores_y_nuevos(): void
    {
        $usuario = Usuario::factory()->conRol(Rol::ENCARGADO)->create(['cargo' => 'Auxiliar']);

        $this->actingAs($this->admin)
            ->put("/panel/usuarios/{$usuario->id_usuario}", $this->datosUsuario([
                'nombre_usuario' => $usuario->nombre_usuario,
                'correo' => $usuario->correo,
                'cargo' => 'Jefe de archivo',
                'roles' => [$this->idRol(Rol::REVISOR)],
            ]))
            ->assertRedirect('/panel/usuarios');

        $usuario->refresh();
        $this->assertTrue($usuario->tieneRol(Rol::REVISOR));
        $this->assertFalse($usuario->tieneRol(Rol::ENCARGADO));

        $detalle = Auditoria::where('accion', Auditoria::MODIFICAR)->where('id_registro', $usuario->id_usuario)->value('detalle');
        $this->assertSame('Auxiliar', $detalle['anterior']['cargo']);
        $this->assertSame('Jefe de archivo', $detalle['nuevo']['cargo']);
        // JSONB no conserva el orden de las claves: se compara el contenido.
        $this->assertEquals(['anterior' => ['ENCARGADO'], 'nuevo' => ['REVISOR']], $detalle['roles']);
    }

    public function test_desactivar_activar_y_desbloquear_una_cuenta(): void
    {
        $usuario = Usuario::factory()->conRol(Rol::REVISOR)->create(['bloqueado_hasta' => now()->addMinutes(10), 'intentos_fallidos' => 2]);
        $this->actingAs($this->admin);

        $this->patch("/panel/usuarios/{$usuario->id_usuario}/desbloquear")->assertSessionHas('exito');
        $this->assertFalse($usuario->refresh()->estaBloqueado());
        $this->assertSame(0, $usuario->intentos_fallidos);

        $this->patch("/panel/usuarios/{$usuario->id_usuario}/desactivar")->assertSessionHas('exito');
        $this->assertFalse($usuario->refresh()->activo);
        $this->assertDatabaseHas('usuario', ['id_usuario' => $usuario->id_usuario]); // no se elimina

        $this->patch("/panel/usuarios/{$usuario->id_usuario}/activar")->assertSessionHas('exito');
        $this->assertTrue($usuario->refresh()->activo);

        foreach ([Auditoria::DESBLOQUEO_CUENTA, Auditoria::DESACTIVAR, Auditoria::ACTIVAR] as $accion) {
            $this->assertDatabaseHas('auditoria', ['accion' => $accion, 'id_registro' => $usuario->id_usuario]);
        }
    }

    public function test_restablecer_contrasena_genera_una_temporal_y_obliga_a_cambiarla(): void
    {
        $usuario = Usuario::factory()->create(['debe_cambiar_contrasena' => false]);

        $this->actingAs($this->admin)
            ->patch("/panel/usuarios/{$usuario->id_usuario}/restablecer-contrasena")
            ->assertSessionHas('contrasena_temporal');

        $usuario->refresh();
        $this->assertTrue($usuario->debe_cambiar_contrasena);
        $this->assertTrue(Hash::check(session('contrasena_temporal')['contrasena'], $usuario->contrasena_hash));
        $this->assertDatabaseHas('auditoria', ['accion' => Auditoria::RESTABLECER_CONTRASENA, 'id_registro' => $usuario->id_usuario]);
    }

    public function test_el_administrador_no_puede_desactivarse_a_si_mismo(): void
    {
        $this->actingAs($this->admin)
            ->patch("/panel/usuarios/{$this->admin->id_usuario}/desactivar")
            ->assertSessionHas('error', 'No puede desactivar su propia cuenta.');

        $this->assertTrue($this->admin->refresh()->activo);
    }

    public function test_no_se_puede_dejar_al_sistema_sin_administradores(): void
    {
        // El administrador del seeder y el creado en setUp: se desactiva el del seeder.
        Usuario::where('nombre_usuario', config('gaceta.admin.nombre_usuario'))->update(['activo' => false]);

        $this->actingAs($this->admin)
            ->put("/panel/usuarios/{$this->admin->id_usuario}", $this->datosUsuario([
                'nombre_usuario' => $this->admin->nombre_usuario,
                'correo' => $this->admin->correo,
                'roles' => [$this->idRol(Rol::REVISOR)],
            ]))
            ->assertSessionHasErrors('roles');

        $this->assertTrue($this->admin->refresh()->tieneRol(Rol::ADMIN));
    }
}
