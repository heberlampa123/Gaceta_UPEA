<?php

namespace Tests\Feature\Auth;

use App\Models\Auditoria;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InicioSesionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function crearUsuario(array $datos = []): Usuario
    {
        return Usuario::factory()->conRol(Rol::ENCARGADO)->create([
            'nombre_usuario' => 'jperez',
            'correo' => 'jperez@upea.edu.bo',
            'contrasena_hash' => 'Clave2026',
            ...$datos,
        ]);
    }

    public function test_la_pantalla_de_inicio_de_sesion_se_muestra(): void
    {
        $this->get('/login')->assertOk()->assertSee('Usuario o correo');
    }

    public function test_el_panel_exige_iniciar_sesion(): void
    {
        $this->get('/panel')->assertRedirect('/login');
        $this->get('/panel/usuarios')->assertRedirect('/login');
    }

    public function test_se_puede_ingresar_con_nombre_de_usuario_o_correo(): void
    {
        $usuario = $this->crearUsuario(['intentos_fallidos' => 3]);

        $this->post('/login', ['usuario' => 'jperez', 'contrasena' => 'Clave2026'])->assertRedirect('/panel');
        $this->assertAuthenticatedAs($usuario);

        $usuario->refresh();
        $this->assertSame(0, $usuario->intentos_fallidos);
        $this->assertNotNull($usuario->ultimo_acceso);
        $this->assertDatabaseHas('auditoria', ['accion' => Auditoria::INICIO_SESION, 'id_usuario' => $usuario->id_usuario]);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseHas('auditoria', ['accion' => Auditoria::CIERRE_SESION, 'id_usuario' => $usuario->id_usuario]);

        $this->post('/login', ['usuario' => 'JPerez@UPEA.edu.bo', 'contrasena' => 'Clave2026'])->assertRedirect('/panel');
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_credenciales_incorrectas_se_rechazan_y_se_auditan(): void
    {
        $usuario = $this->crearUsuario();

        $this->from('/login')->post('/login', ['usuario' => 'jperez', 'contrasena' => 'mala'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['usuario' => 'El usuario o la contraseña son incorrectos.']);

        $this->post('/login', ['usuario' => 'noexiste', 'contrasena' => 'mala'])
            ->assertSessionHasErrors(['usuario' => 'El usuario o la contraseña son incorrectos.']);

        $this->assertGuest();
        $this->assertSame(1, $usuario->refresh()->intentos_fallidos);
        $this->assertSame(2, Auditoria::where('accion', Auditoria::INICIO_SESION_FALLIDO)->count());
        $this->assertDatabaseHas('auditoria', ['accion' => Auditoria::INICIO_SESION_FALLIDO, 'id_usuario' => null]);
    }

    public function test_la_cuenta_se_bloquea_tras_varios_intentos_y_se_libera_sola(): void
    {
        $usuario = $this->crearUsuario();

        for ($i = 1; $i <= Usuario::MAX_INTENTOS_FALLIDOS; $i++) {
            $this->post('/login', ['usuario' => 'jperez', 'contrasena' => 'mala']);
        }

        $this->assertTrue($usuario->refresh()->estaBloqueado());
        $this->assertDatabaseHas('auditoria', ['accion' => Auditoria::BLOQUEO_CUENTA, 'id_registro' => $usuario->id_usuario]);

        // Con la contraseña correcta tampoco puede ingresar mientras dure el bloqueo.
        $this->post('/login', ['usuario' => 'jperez', 'contrasena' => 'Clave2026'])
            ->assertSessionHasErrors('usuario');
        $this->assertGuest();

        $this->travel(Usuario::MINUTOS_BLOQUEO + 1)->minutes();

        $this->post('/login', ['usuario' => 'jperez', 'contrasena' => 'Clave2026'])->assertRedirect('/panel');
        $this->assertAuthenticatedAs($usuario);
        $this->assertNull($usuario->refresh()->bloqueado_hasta);
    }

    public function test_una_cuenta_desactivada_no_puede_ingresar(): void
    {
        $this->crearUsuario(['activo' => false]);

        $this->post('/login', ['usuario' => 'jperez', 'contrasena' => 'Clave2026'])
            ->assertSessionHasErrors(['usuario' => 'Su cuenta está desactivada. Comuníquese con el administrador.']);

        $this->assertGuest();
    }

    public function test_una_cuenta_desactivada_durante_la_sesion_es_expulsada(): void
    {
        $usuario = $this->crearUsuario();
        $this->actingAs($usuario)->get('/panel')->assertOk();

        $usuario->forceFill(['activo' => false])->save();

        $this->get('/panel')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_con_contrasena_temporal_se_obliga_a_cambiarla(): void
    {
        $usuario = $this->crearUsuario(['debe_cambiar_contrasena' => true]);

        $this->actingAs($usuario)->get('/panel')->assertRedirect('/panel/perfil/contrasena');
        $this->get('/panel/perfil/contrasena')->assertOk()->assertSee('contraseña temporal');
    }
}
