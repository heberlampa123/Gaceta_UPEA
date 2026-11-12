<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Usuario;
use App\Support\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Inicio y cierre de sesión del panel administrativo (Ref. 1, 2 y 6).
 *
 * Reglas:
 *  - Se ingresa con el nombre de usuario o el correo.
 *  - Tras MAX_INTENTOS_FALLIDOS intentos consecutivos, la cuenta se bloquea
 *    MINUTOS_BLOQUEO minutos (bloqueado_hasta). El administrador puede desbloquearla antes.
 *  - Cada intento (exitoso o fallido) queda registrado en auditoría.
 */
class LoginController extends Controller
{
    private const MENSAJE_CREDENCIALES = 'El usuario o la contraseña son incorrectos.';

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'usuario' => ['required', 'string', 'max:150'],
            'contrasena' => ['required', 'string'],
        ], [], [
            'usuario' => 'usuario o correo',
            'contrasena' => 'contraseña',
        ]);

        $ingresado = trim($datos['usuario']);

        $usuario = Usuario::query()
            ->where('nombre_usuario', $ingresado)
            ->orWhereRaw('lower(correo) = ?', [mb_strtolower($ingresado)])
            ->first();

        if ($usuario?->estaBloqueado()) {
            $this->registrarFallo($ingresado, $usuario, 'cuenta_bloqueada');

            throw ValidationException::withMessages([
                'usuario' => $this->mensajeBloqueo($usuario),
            ]);
        }

        if (! $usuario || ! Hash::check($datos['contrasena'], $usuario->contrasena_hash)) {
            $this->registrarFallo($ingresado, $usuario, 'credenciales_incorrectas');

            if ($usuario && $this->sumarIntentoFallido($usuario)) {
                throw ValidationException::withMessages(['usuario' => $this->mensajeBloqueo($usuario)]);
            }

            throw ValidationException::withMessages(['usuario' => self::MENSAJE_CREDENCIALES]);
        }

        if (! $usuario->activo) {
            $this->registrarFallo($ingresado, $usuario, 'cuenta_desactivada');

            throw ValidationException::withMessages([
                'usuario' => 'Su cuenta está desactivada. Comuníquese con el administrador.',
            ]);
        }

        Auth::login($usuario);
        $request->session()->regenerate();

        $usuario->forceFill([
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            'ultimo_acceso' => now(),
        ])->save();

        Auditor::registrar(Auditoria::INICIO_SESION, 'Usuario', $usuario->id_usuario);

        return redirect()->intended(route('panel.inicio'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($id = Auth::id()) {
            Auditor::registrar(Auditoria::CIERRE_SESION, 'Usuario', $id);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('estado', 'Sesión cerrada correctamente.');
    }

    /** Incrementa los intentos fallidos; devuelve true si la cuenta quedó bloqueada. */
    private function sumarIntentoFallido(Usuario $usuario): bool
    {
        $intentos = $usuario->intentos_fallidos + 1;

        if ($intentos >= Usuario::MAX_INTENTOS_FALLIDOS) {
            $usuario->forceFill([
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => now()->addMinutes(Usuario::MINUTOS_BLOQUEO),
            ])->save();

            Auditor::registrar(Auditoria::BLOQUEO_CUENTA, 'Usuario', $usuario->id_usuario, [
                'motivo' => 'intentos_fallidos',
                'bloqueado_hasta' => $usuario->bloqueado_hasta->toDateTimeString(),
            ], $usuario->id_usuario);

            return true;
        }

        $usuario->forceFill(['intentos_fallidos' => $intentos])->save();

        return false;
    }

    private function registrarFallo(string $ingresado, ?Usuario $usuario, string $motivo): void
    {
        Auditor::registrar(
            Auditoria::INICIO_SESION_FALLIDO,
            'Usuario',
            $usuario?->id_usuario,
            ['usuario_ingresado' => mb_substr($ingresado, 0, 150), 'motivo' => $motivo],
            $usuario?->id_usuario,
        );
    }

    private function mensajeBloqueo(Usuario $usuario): string
    {
        return sprintf(
            'La cuenta está bloqueada temporalmente por varios intentos fallidos. Intente nuevamente a las %s o solicite el desbloqueo al administrador.',
            $usuario->bloqueado_hasta->format('H:i'),
        );
    }
}
