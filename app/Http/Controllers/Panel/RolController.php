<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarRolRequest;
use App\Models\Auditoria;
use App\Models\Permiso;
use App\Models\Rol;
use App\Support\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Administración de roles y asignación de permisos (RF-01, Ref. 4).
 * El catálogo de permisos es fijo (lo usa el código); los roles solo los agrupan.
 * Los roles no se eliminan: se desactivan.
 */
class RolController extends Controller
{
    /** Permisos que el rol ADMIN conserva siempre, para no perder el acceso a la administración. */
    private const PERMISOS_PROTEGIDOS_ADMIN = ['usuario.gestionar', 'rol.gestionar'];

    public function index(): View
    {
        return view('panel.roles.index', [
            'roles' => Rol::withCount(['usuarios', 'permisos'])->orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        return view('panel.roles.create', [
            'rol' => new Rol(['activo' => true]),
            'permisosPorModulo' => $this->permisosPorModulo(),
            'asignados' => [],
        ]);
    }

    public function store(GuardarRolRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $rol = DB::transaction(function () use ($datos) {
            $rol = Rol::create(collect($datos)->only(['codigo', 'nombre', 'descripcion', 'activo'])->all());
            $rol->permisos()->sync($datos['permisos'] ?? []);

            Auditor::creacion($rol, ['permisos' => $this->codigosPermiso($datos['permisos'] ?? [])]);

            return $rol;
        });

        return redirect()->route('panel.roles.index')->with('exito', "Se creó el rol {$rol->nombre}.");
    }

    public function edit(Rol $rol): View
    {
        return view('panel.roles.edit', [
            'rol' => $rol,
            'permisosPorModulo' => $this->permisosPorModulo(),
            'asignados' => $rol->permisos()->pluck('permiso.id_permiso')->all(),
        ]);
    }

    public function update(GuardarRolRequest $request, Rol $rol): RedirectResponse
    {
        $datos = $request->validated();
        $permisosNuevos = array_map('intval', $datos['permisos'] ?? []);
        $codigosNuevos = $this->codigosPermiso($permisosNuevos);

        if ($rol->codigo === Rol::ADMIN) {
            if (! $datos['activo']) {
                throw ValidationException::withMessages(['activo' => 'El rol Administrador no puede desactivarse.']);
            }

            if ($faltantes = array_diff(self::PERMISOS_PROTEGIDOS_ADMIN, $codigosNuevos)) {
                throw ValidationException::withMessages([
                    'permisos' => 'El rol Administrador debe conservar los permisos: '.implode(', ', $faltantes).'.',
                ]);
            }
        }

        DB::transaction(function () use ($rol, $datos, $permisosNuevos, $codigosNuevos) {
            $codigosAnteriores = $rol->permisos()->orderBy('codigo')->pluck('codigo')->all();

            $rol->fill(collect($datos)->only(['nombre', 'descripcion', 'activo'])->all())->save();
            $rol->permisos()->sync($permisosNuevos);

            Auditor::modificacion($rol);

            if ($codigosAnteriores !== $codigosNuevos) {
                Auditor::registrar(Auditoria::CAMBIO_PERMISOS, 'Rol', $rol->id_rol, [
                    'anterior' => $codigosAnteriores,
                    'nuevo' => $codigosNuevos,
                ]);
            }
        });

        return redirect()->route('panel.roles.index')->with('exito', "Se actualizó el rol {$rol->nombre}.");
    }

    private function permisosPorModulo()
    {
        return Permiso::orderBy('modulo')->orderBy('codigo')->get()->groupBy('modulo');
    }

    private function codigosPermiso(array $idsPermiso): array
    {
        return Permiso::whereIn('id_permiso', $idsPermiso)->orderBy('codigo')->pluck('codigo')->all();
    }
}
