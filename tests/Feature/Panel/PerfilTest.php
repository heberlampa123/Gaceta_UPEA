<?php

namespace Tests\Feature\Panel;

use App\Models\Auditoria;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PerfilTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_el_usuario_ve_su_perfil_y_sus_permisos(): void
    {
        $usuario = Usuario::factory()->conRol(Rol::REVISOR)->create();

        $this->actingAs($usuario)->get('/panel/perfil')
            ->assertOk()
            ->assertSee($usuario->nombre_usuario)
            ->assertSee('documento.publicar');
    }

    public function test_cambiar_la_contrasena_exige_la_actual_y_cumplir_la_politica(): void
    {
        $usuario = Usuario::factory()->create(['contrasena_hash' => 'Actual2026']);
        $this->actingAs($usuario);

        $this->put('/panel/perfil/contrasena', [
            'contrasena_actual' => 'equivocada',
            'contrasena' => 'Nueva2026',
            'contrasena_confirmation' => 'Nueva2026',
        ])->assertSessionHasErrors('contrasena_actual');

        $this->put('/panel/perfil/contrasena', [
            'contrasena_actual' => 'Actual2026',
            'contrasena' => 'solotexto',
            'contrasena_confirmation' => 'solotexto',
        ])->assertSessionHasErrors('contrasena');

        $this->put('/panel/perfil/contrasena', [
            'contrasena_actual' => 'Actual2026',
            'contrasena' => 'Actual2026',
            'contrasena_confirmation' => 'Actual2026',
        ])->assertSessionHasErrors('contrasena');
    }

    public function test_al_cambiar_la_contrasena_temporal_se_libera_el_panel(): void
    {
        $usuario = Usuario::factory()->create(['contrasena_hash' => 'Temporal99', 'debe_cambiar_contrasena' => true]);

        $this->actingAs($usuario)->put('/panel/perfil/contrasena', [
            'contrasena_actual' => 'Temporal99',
            'contrasena' => 'Definitiva2026',
            'contrasena_confirmation' => 'Definitiva2026',
        ])->assertRedirect('/panel/perfil');

        $usuario->refresh();
        $this->assertFalse($usuario->debe_cambiar_contrasena);
        $this->assertTrue(Hash::check('Definitiva2026', $usuario->contrasena_hash));
        $this->assertDatabaseHas('auditoria', ['accion' => Auditoria::CAMBIO_CONTRASENA, 'id_usuario' => $usuario->id_usuario]);

        $this->get('/panel')->assertOk();
    }
}
