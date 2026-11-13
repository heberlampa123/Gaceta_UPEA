<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\EstadoDocumento;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Inicio del panel (Figura 2): resumen de documentos, pendientes según el rol y actividad reciente.
 * Cada bloque aparece solo si el usuario tiene el permiso correspondiente.
 */
class InicioController extends Controller
{
    public function __invoke(Request $request): View
    {
        $usuario = $request->user();
        $id = $usuario->id_usuario;

        $estados = $usuario->can('documento.ver')
            ? EstadoDocumento::where('activo', true)
                ->withCount(['documentos' => fn ($q) => $q->where('activo', true)])
                ->orderBy('orden')
                ->get()
            : collect();

        // Pendientes según el rol. Cada uno: [texto, cantidad, enlace, ícono, color].
        $pendientes = collect();

        if ($usuario->can('documento.revisar')) {
            $deOtros = fn (string $estado) => Documento::activos()->enEstado($estado)->where('id_usuario_registro', '<>', $id)->count();
            $pendientes->push(['Documentos por revisar', $deOtros(EstadoDocumento::PENDIENTE_REVISION), route('panel.revision.index'), 'clipboard-check', 'warning']);

            if ($usuario->can('documento.publicar')) {
                $pendientes->push(['Aprobados por publicar', $deOtros(EstadoDocumento::APROBADO), route('panel.revision.index', ['bandeja' => 'aprobados']), 'globe', 'info']);
            }
        }

        if ($usuario->can('observacion.atender')) {
            $pendientes->push(['Mis documentos observados', Documento::activos()->enEstado(EstadoDocumento::OBSERVADO)->where('id_usuario_registro', $id)->count(),
                route('panel.documentos.index', ['estado' => EstadoDocumento::OBSERVADO]), 'exclamation-circle', 'danger']);
        }

        if ($usuario->can('documento.registrar')) {
            $pendientes->push(['Mis borradores sin enviar', Documento::activos()->enEstado(EstadoDocumento::BORRADOR)->where('id_usuario_registro', $id)->count(),
                route('panel.documentos.index', ['estado' => EstadoDocumento::BORRADOR]), 'pencil-square', 'secondary']);
        }

        if ($usuario->can('usuario.gestionar')) {
            $pendientes->push(['Cuentas bloqueadas', Usuario::where('bloqueado_hasta', '>', now())->count(),
                route('panel.usuarios.index', ['estado' => 'bloqueados']), 'lock', 'danger']);
        }

        return view('panel.inicio', [
            'usuario' => $usuario,
            'estados' => $estados,
            'pendientes' => $pendientes,
            'notificaciones' => $usuario->notificaciones()->noLeidas()->latest('fecha_creacion')->limit(5)->get(),
            'recientes' => $usuario->can('documento.ver')
                ? Documento::activos()->with(['estadoActual', 'tipoDocumental'])->latest('fecha_actualizacion')->limit(5)->get()
                : collect(),
            'actividad' => Auditoria::where('id_usuario', $id)->latest('fecha')->latest('id_auditoria')->limit(5)->get(),
        ]);
    }
}
