<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\EstadoDocumento;
use App\Models\Rol;
use App\Models\SeguimientoDocumento;
use App\Models\TipoDocumental;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $verDocumentos = $usuario->can('documento.ver');
        $filtros = array_filter($request->only(['buscar', 'gestion', 'tipo', 'estado', 'fecha']), fn ($v) => is_string($v) && $v !== '');

        return view('panel.inicio', [
            'usuario' => $usuario,
            // Los indicadores generales (totales por estado) solo los ve el Administrador.
            'indicadores' => $usuario->tieneRol(Rol::ADMIN) ? $this->indicadores($estados) : collect(),
            'pendientes' => $pendientes,
            'filtros' => $filtros,
            'movimientos' => $verDocumentos ? $this->actividadDocumental($filtros) : collect(),
            'gestiones' => $verDocumentos ? Documento::activos()->distinct()->orderByDesc('gestion')->pluck('gestion') : collect(),
            'tipos' => TipoDocumental::orderBy('nombre')->get(['id_tipo_documental', 'nombre']),
            'estados' => $estados,
            'actividad' => Auditoria::where('id_usuario', $id)->latest('fecha')->latest('id_auditoria')->limit(5)->get(),
        ]);
    }

    /**
     * Indicadores del modelo (Figura 2): total y tres estados clave; cada uno abre la lista filtrada.
     * Cada uno: [título, cantidad, descripción, ícono, enlace, texto del enlace].
     */
    private function indicadores(Collection $estados): Collection
    {
        $cantidad = fn (string $codigo) => (int) $estados->firstWhere('codigo', $codigo)?->documentos_count;
        $lista = fn (?string $codigo = null) => route('panel.documentos.index', array_filter(['estado' => $codigo]));

        return collect([
            ['Documentos', (int) $estados->sum('documentos_count'), 'Registrados en el sistema', 'file-earmark-text', $lista(), 'Ver todos'],
            ['Pendientes', $cantidad(EstadoDocumento::PENDIENTE_REVISION), 'En espera de revisión', 'hourglass-split', $lista(EstadoDocumento::PENDIENTE_REVISION), 'Ver pendientes'],
            ['Aprobados', $cantidad(EstadoDocumento::APROBADO), 'Listos para publicar', 'check2-circle', $lista(EstadoDocumento::APROBADO), 'Ver aprobados'],
            ['Publicados', $cantidad(EstadoDocumento::PUBLICADO), 'Visibles en el portal público', 'globe2', $lista(EstadoDocumento::PUBLICADO), 'Ver publicados'],
        ]);
    }

    /** Últimos movimientos documentales (registro, envío, observación, aprobación, publicación…). */
    private function actividadDocumental(array $filtros): Collection
    {
        $fecha = isset($filtros['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtros['fecha']) ? $filtros['fecha'] : null;

        return SeguimientoDocumento::query()
            ->with(['documento.tipoDocumental', 'usuario.roles', 'estadoNuevo', 'version'])
            ->whereHas('documento', fn (Builder $d) => $d->activos()
                ->when($filtros['buscar'] ?? null, fn (Builder $q, string $t) => $q->where(fn (Builder $w) => $w
                    ->buscar($t)->orWhere('numero', 'ilike', '%'.$t.'%')->orWhere('titulo', 'ilike', '%'.$t.'%')))
                ->when($filtros['gestion'] ?? null, fn (Builder $q, $g) => $q->where('gestion', (int) $g))
                ->when($filtros['tipo'] ?? null, fn (Builder $q, $t) => $q->where('id_tipo_documental', (int) $t)))
            ->when($filtros['estado'] ?? null, fn (Builder $q, string $e) => $q->whereHas('estadoNuevo', fn (Builder $s) => $s->where('codigo', $e)))
            ->when($fecha, fn (Builder $q) => $q->whereDate('fecha', $fecha))
            ->latest('fecha')
            ->latest('id_seguimiento')
            ->limit(8)
            ->get();
    }
}
