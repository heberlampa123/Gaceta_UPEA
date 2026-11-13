<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** Comando gaceta:token: tokens de la API para clientes externos como Bruno. */
class TokenApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_crea_un_token_que_da_acceso_a_la_api_y_se_puede_revocar(): void
    {
        $usuario = Usuario::factory()->conRol(Rol::REVISOR)->create(['nombre_usuario' => 'mquispe']);

        $this->assertSame(0, Artisan::call('gaceta:token', ['usuario' => 'MQuispe']));
        preg_match('/\d+\|[A-Za-z0-9]{20,}/', Artisan::output(), $m);
        $token = $m[0] ?? $this->fail('El comando no mostró el token.');

        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('nombre_usuario', 'mquispe');
        $this->withToken($token)->getJson('/api/notificaciones/resumen')->assertOk()->assertJsonStructure(['no_leidas', 'recientes']);
        $this->assertNotNull($usuario->tokens()->first()->expires_at);

        $this->assertSame(0, Artisan::call('gaceta:token', ['usuario' => 'mquispe', '--revocar' => true]));
        $this->assertSame(0, $usuario->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_falla_con_un_usuario_inexistente_o_desactivado(): void
    {
        $this->assertSame(1, Artisan::call('gaceta:token', ['usuario' => 'nadie']));

        Usuario::factory()->conRol(Rol::REVISOR)->create(['nombre_usuario' => 'inactivo', 'activo' => false]);
        $this->assertSame(1, Artisan::call('gaceta:token', ['usuario' => 'inactivo']));
    }
}
