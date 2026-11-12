<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Support\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Mi perfil: datos de la cuenta y cambio de contraseña (Ref. 27). */
class PerfilController extends Controller
{
    public function show(Request $request): View
    {
        return view('panel.perfil.show', [
            'usuario' => $request->user()->load('roles'),
            'permisos' => $request->user()->codigosPermiso(),
        ]);
    }

    public function editarContrasena(Request $request): View
    {
        return view('panel.perfil.contrasena', [
            'obligatorio' => $request->user()->debe_cambiar_contrasena,
        ]);
    }

    public function actualizarContrasena(Request $request): RedirectResponse
    {
        $request->validate([
            'contrasena_actual' => ['required', 'current_password:web'],
            'contrasena' => ['required', 'confirmed', 'different:contrasena_actual', Password::defaults()],
        ], [
            'contrasena.different' => 'La nueva contraseña debe ser distinta de la actual.',
        ], [
            'contrasena_actual' => 'contraseña actual',
            'contrasena' => 'nueva contraseña',
        ]);

        $request->user()->forceFill([
            'contrasena_hash' => $request->input('contrasena'),
            'debe_cambiar_contrasena' => false,
        ])->save();

        Auditor::registrar(Auditoria::CAMBIO_CONTRASENA, 'Usuario', $request->user()->id_usuario);

        return redirect()->route('panel.perfil')->with('exito', 'Su contraseña se actualizó correctamente.');
    }
}
