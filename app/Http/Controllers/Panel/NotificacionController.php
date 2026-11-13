<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Página «Notificaciones» del panel (Figura 2; Ref. 24: gestión de notificaciones). */
class NotificacionController extends Controller
{
    public function index(Request $request): View
    {
        $soloNoLeidas = $request->boolean('no_leidas');

        $notificaciones = $request->user()->notificaciones()
            ->with('documento.tipoDocumental')
            ->when($soloNoLeidas, fn ($q) => $q->noLeidas())
            ->latest('fecha_creacion')
            ->latest('id_notificacion')
            ->paginate(20)
            ->withQueryString();

        return view('panel.notificaciones.index', [
            'notificaciones' => $notificaciones,
            'soloNoLeidas' => $soloNoLeidas,
            'noLeidas' => $request->user()->notificaciones()->noLeidas()->count(),
        ]);
    }

    /** Marca la notificación como leída y abre el documento relacionado. */
    public function abrir(Request $request, Notificacion $notificacion): RedirectResponse
    {
        abort_unless($notificacion->id_usuario_destino === $request->user()->id_usuario, 404);

        $notificacion->marcarComoLeida();

        return $notificacion->id_documento
            ? redirect()->route('panel.documentos.show', $notificacion->id_documento)
            : redirect()->route('panel.notificaciones.index');
    }

    public function leerTodas(Request $request): RedirectResponse
    {
        $request->user()->notificaciones()->noLeidas()->update(['fecha_lectura' => now()]);

        return back()->with('exito', 'Todas las notificaciones se marcaron como leídas.');
    }
}
