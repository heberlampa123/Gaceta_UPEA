<?php

namespace App\Providers;

use App\Models\Usuario;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Política de contraseñas: mínimo 8 caracteres, con letras y números.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Límite de intentos de inicio de sesión por dirección IP (además del bloqueo por cuenta).
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        /*
         * Autorización por código de permiso (4.4.5): cualquier habilidad con forma
         * "modulo.accion" se resuelve con los permisos de los roles del usuario.
         * Permite usar @can('usuario.gestionar'), $this->authorize(...) y el
         * middleware "can:usuario.gestionar" sin definir cada Gate a mano.
         */
        Gate::before(function (Usuario $usuario, string $habilidad) {
            if (str_contains($habilidad, '.')) {
                return $usuario->tienePermiso($habilidad);
            }

            return null;
        });
    }
}
