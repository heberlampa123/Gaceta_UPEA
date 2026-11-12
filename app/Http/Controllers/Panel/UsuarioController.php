<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarUsuarioRequest;
use App\Models\Auditoria;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use App\Support\Auditor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Administración de cuentas (RF-01). Las cuentas solo las crea el administrador
 * y no se eliminan: se desactivan para conservar la trazabilidad.
 */
class UsuarioController extends Controller
{
    /** Permiso que debe conservar al menos un usuario activo para no perder el control del sistema. */
    private const PERMISO_CRITICO = 'usuario.gestionar';

    public function index(Request $request): View
    {
        $filtros = $request->only(['buscar', 'estado', 'rol']);

        $usuarios = Usuario::query()
            ->with('roles')
            ->when($filtros['buscar'] ?? null, function (Builder $q, string $texto) {
                $q->where(fn (Builder $w) => $w
                    ->where('nombres', 'ilike', "%{$texto}%")
                    ->orWhere('apellidos', 'ilike', "%{$texto}%")
                    ->orWhere('nombre_usuario', 'ilike', "%{$texto}%")
                    ->orWhere('correo', 'ilike', "%{$texto}%"));
            })
            ->when($filtros['estado'] ?? null, fn (Builder $q, string $estado) => match ($estado) {
                'activos' => $q->where('activo', true),
                'inactivos' => $q->where('activo', false),
                'bloqueados' => $q->where('bloqueado_hasta', '>', now()),
                default => $q,
            })
            ->when($filtros['rol'] ?? null, fn (Builder $q, $idRol) => $q->whereHas('roles', fn (Builder $r) => $r->where('rol.id_rol', $idRol)))
            ->orderByDesc('activo')
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->paginate(15)
            ->withQueryString();

        return view('panel.usuarios.index', [
            'usuarios' => $usuarios,
            'roles' => Rol::orderBy('nombre')->get(),
            'filtros' => $filtros,
        ]);
    }

    public function create(): View
    {
        return view('panel.usuarios.create', [
            'usuario' => new Usuario,
            'roles' => Rol::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(GuardarUsuarioRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $contrasena = ($datos['contrasena'] ?? null) ?: Str::password(10, symbols: false);

        $usuario = DB::transaction(function () use ($datos, $contrasena) {
            $usuario = Usuario::create([
                ...collect($datos)->only(['nombres', 'apellidos', 'correo', 'nombre_usuario', 'cargo'])->all(),
                'contrasena_hash' => $contrasena,
                'activo' => true,
                'debe_cambiar_contrasena' => true,
            ]);

            $usuario->roles()->sync($datos['roles']);

            Auditor::creacion($usuario, ['roles' => $this->codigosRol($datos['roles'])]);

            return $usuario;
        });

        return redirect()->route('panel.usuarios.index')
            ->with('exito', "Se creó la cuenta de {$usuario->nombre_completo}.")
            ->with('contrasena_temporal', ['usuario' => $usuario->nombre_usuario, 'contrasena' => $contrasena]);
    }

    public function edit(Usuario $usuario): View
    {
        return view('panel.usuarios.edit', [
            'usuario' => $usuario->load('roles'),
            'roles' => Rol::where('activo', true)->orWhereIn('id_rol', $usuario->roles->pluck('id_rol'))->orderBy('nombre')->get(),
        ]);
    }

    public function update(GuardarUsuarioRequest $request, Usuario $usuario): RedirectResponse
    {
        $datos = $request->validated();
        $rolesNuevos = array_map('intval', $datos['roles']);
        $rolesAnteriores = $usuario->roles()->pluck('rol.id_rol')->all();

        if ($usuario->tienePermiso(self::PERMISO_CRITICO)
            && ! $this->rolesOtorgan($rolesNuevos, self::PERMISO_CRITICO)
            && ! $this->hayOtroAdministrador($usuario)) {
            throw ValidationException::withMessages([
                'roles' => 'No se puede quitar el rol de administración: es el único usuario activo que puede gestionar cuentas.',
            ]);
        }

        DB::transaction(function () use ($usuario, $datos, $rolesNuevos, $rolesAnteriores) {
            $usuario->fill(collect($datos)->only(['nombres', 'apellidos', 'correo', 'nombre_usuario', 'cargo'])->all())->save();
            $usuario->roles()->sync($rolesNuevos);

            sort($rolesAnteriores);
            sort($rolesNuevos);
            $extra = $rolesAnteriores === $rolesNuevos ? [] : ['roles' => [
                'anterior' => $this->codigosRol($rolesAnteriores),
                'nuevo' => $this->codigosRol($rolesNuevos),
            ]];

            Auditor::modificacion($usuario, $extra);
        });

        return redirect()->route('panel.usuarios.index')->with('exito', "Se actualizó la cuenta de {$usuario->nombre_completo}.");
    }

    public function desactivar(Request $request, Usuario $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('error', 'No puede desactivar su propia cuenta.');
        }

        if ($usuario->tienePermiso(self::PERMISO_CRITICO) && ! $this->hayOtroAdministrador($usuario)) {
            return back()->with('error', 'No se puede desactivar al único usuario activo que puede gestionar cuentas.');
        }

        DB::transaction(function () use ($usuario) {
            $usuario->forceFill(['activo' => false])->save();
            $usuario->tokens()->delete();
            Auditor::modificacion($usuario, accion: Auditoria::DESACTIVAR);
        });

        return back()->with('exito', "Se desactivó la cuenta de {$usuario->nombre_completo}.");
    }

    public function activar(Usuario $usuario): RedirectResponse
    {
        $usuario->forceFill(['activo' => true])->save();
        Auditor::modificacion($usuario, accion: Auditoria::ACTIVAR);

        return back()->with('exito', "Se activó la cuenta de {$usuario->nombre_completo}.");
    }

    public function desbloquear(Usuario $usuario): RedirectResponse
    {
        $usuario->forceFill(['bloqueado_hasta' => null, 'intentos_fallidos' => 0])->save();
        Auditor::modificacion($usuario, accion: Auditoria::DESBLOQUEO_CUENTA);

        return back()->with('exito', "Se desbloqueó la cuenta de {$usuario->nombre_completo}.");
    }

    /** El administrador asigna una contraseña temporal; el usuario debe cambiarla al ingresar. */
    public function restablecerContrasena(Usuario $usuario): RedirectResponse
    {
        $contrasena = Str::password(10, symbols: false);

        $usuario->forceFill([
            'contrasena_hash' => $contrasena,
            'debe_cambiar_contrasena' => true,
            'bloqueado_hasta' => null,
            'intentos_fallidos' => 0,
        ])->save();

        Auditor::registrar(Auditoria::RESTABLECER_CONTRASENA, 'Usuario', $usuario->id_usuario);

        return back()
            ->with('exito', "Se restableció la contraseña de {$usuario->nombre_completo}.")
            ->with('contrasena_temporal', ['usuario' => $usuario->nombre_usuario, 'contrasena' => $contrasena]);
    }

    // ----------------------------------------------------------------- Utilidades

    private function codigosRol(array $idsRol): array
    {
        return Rol::whereIn('id_rol', $idsRol)->orderBy('codigo')->pluck('codigo')->all();
    }

    private function rolesOtorgan(array $idsRol, string $codigoPermiso): bool
    {
        return Permiso::where('codigo', $codigoPermiso)
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('rol.id_rol', $idsRol)->where('rol.activo', true))
            ->exists();
    }

    private function hayOtroAdministrador(Usuario $excluido): bool
    {
        return Usuario::activos()
            ->whereKeyNot($excluido->id_usuario)
            ->whereHas('roles', fn (Builder $r) => $r
                ->where('rol.activo', true)
                ->whereHas('permisos', fn (Builder $p) => $p->where('codigo', self::PERMISO_CRITICO)))
            ->exists();
    }
}
