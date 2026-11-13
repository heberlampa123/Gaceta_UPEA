<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Las pruebas cambian de usuario dentro de una misma sesión (por ejemplo, de Encargado a Revisor).
     * En el sistema real eso exige cerrar sesión e ingresar de nuevo; aquí se descarta la huella de
     * contraseña que guarda el middleware auth.session para que no expulse al nuevo usuario.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($this->app->bound('session.store')) {
            $this->app['session.store']->forget('password_hash_'.($guard ?? 'web'));
        }

        return parent::actingAs($user, $guard);
    }

    /**
     * En el sistema real cada petición empieza con el guardia "web". En las pruebas la aplicación se
     * reutiliza entre peticiones y, tras una llamada a la API, el guardia queda en "sanctum".
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->setDefaultDriver('web');

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
