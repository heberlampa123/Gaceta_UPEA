<?php

namespace Tests\Feature\Panel;

use App\Models\Auditoria;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolAdministracionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Usuario::factory()->conRol(Rol::ADMIN)->create();
    }

    private function idsPermiso(array $codigos): array
    {
        return Permiso::whereIn('codigo', $codigos)->pluck('id_permiso')->all();
    }

    public function test_solo_quien_tiene_el_permiso_puede_administrar_roles(): void
    {
        $revisor = Usuario::factory()->conRol(Rol::REVISOR)->create();

        $this->actingAs($revisor)->get('/panel/roles')->assertForbidden();
        $this->actingAs($this->admin)->get('/panel/roles')->assertOk()->assertSee('Revisor / Publicador');
    }

    public function test_un_rol_nuevo_otorga_sus_permisos_sin_modificar_el_programa(): void
    {
        $this->actingAs($this->admin)->post('/panel/roles', [
            'codigo' => 'consulta',
            'nombre' => 'Consulta',
            'descripcion' => 'Solo consulta documentos.',
            'activo' => '1',
            'permisos' => $this->idsPermiso(['documento.ver']),
        ])->assertRedirect('/panel/roles');

        $rol = Rol::where('codigo', 'CONSULTA')->firstOrFail();
        $usuario = Usuario::factory()->create();
        $usuario->roles()->attach($rol->id_rol);

        $this->assertTrue($usuario->tienePermiso('documento.ver'));
        $this->assertFalse($usuario->tienePermiso('documento.registrar'));
        $this->assertDatabaseHas('auditoria', ['accion' => Auditoria::CREAR, 'entidad' => 'Rol', 'id_registro' => $rol->id_rol]);
    }

    public function test_cambiar_los_permisos_de_un_rol_se_audita(): void
    {
        $rol = Rol::where('codigo', Rol::REVISOR)->firstOrFail();

        $this->actingAs($this->admin)->put("/panel/roles/{$rol->id_rol}", [
            'nombre' => $rol->nombre,
            'descripcion' => $rol->descripcion,
            'activo' => '1',
            'permisos' => $this->idsPermiso(['documento.ver', 'documento.revisar']),
        ])->assertRedirect('/panel/roles');

        $detalle = Auditoria::where('accion', Auditoria::CAMBIO_PERMISOS)->where('id_registro', $rol->id_rol)->value('detalle');
        $this->assertContains('documento.publicar', $detalle['anterior']);
        $this->assertSame(['documento.revisar', 'documento.ver'], $detalle['nuevo']);
    }

    public function test_el_codigo_de_un_rol_no_puede_modificarse(): void
    {
        $rol = Rol::where('codigo', Rol::REVISOR)->firstOrFail();

        $this->actingAs($this->admin)->put("/panel/roles/{$rol->id_rol}", [
            'codigo' => 'OTRO',
            'nombre' => $rol->nombre,
            'activo' => '1',
        ])->assertSessionHasErrors('codigo');
    }

    public function test_el_rol_administrador_esta_protegido(): void
    {
        $rol = Rol::where('codigo', Rol::ADMIN)->firstOrFail();

        $this->actingAs($this->admin)->put("/panel/roles/{$rol->id_rol}", [
            'nombre' => 'Administrador',
            'activo' => '0',
            'permisos' => $this->idsPermiso(['usuario.gestionar', 'rol.gestionar']),
        ])->assertSessionHasErrors('activo');

        $this->actingAs($this->admin)->put("/panel/roles/{$rol->id_rol}", [
            'nombre' => 'Administrador',
            'activo' => '1',
            'permisos' => $this->idsPermiso(['auditoria.ver']),
        ])->assertSessionHasErrors('permisos');

        $this->assertTrue($this->admin->tienePermiso('rol.gestionar'));
    }
}
