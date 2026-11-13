<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consulta de auditoría (RF-09, Ref. 26): operaciones realizadas por los usuarios.
 * Solo lectura: los registros de auditoría no se modifican ni eliminan.
 */
class AuditoriaController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $this->filtros($request);

        return view('panel.auditoria.index', [
            'registros' => $this->consulta($filtros)->with('usuario')->paginate(25)->withQueryString(),
            'filtros' => $filtros,
            'usuarios' => Usuario::orderBy('apellidos')->orderBy('nombres')->get(['id_usuario', 'nombres', 'apellidos', 'nombre_usuario']),
            'acciones' => Auditoria::query()->distinct()->orderBy('accion')->pluck('accion')
                ->mapWithKeys(fn ($a) => [$a => Auditoria::ETIQUETAS[$a] ?? $a]),
            'entidades' => Auditoria::query()->whereNotNull('entidad')->distinct()->orderBy('entidad')->pluck('entidad'),
        ]);
    }

    /** Descarga en CSV (Excel) de los registros filtrados. */
    public function exportar(Request $request): StreamedResponse
    {
        $consulta = $this->consulta($this->filtros($request))->with('usuario');

        return response()->streamDownload(function () use ($consulta) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF"); // BOM: Excel reconoce las tildes
            fputcsv($salida, ['Fecha', 'Usuario', 'Acción', 'Entidad', 'ID registro', 'IP', 'Detalle'], ';');

            $consulta->chunk(500, function ($registros) use ($salida) {
                foreach ($registros as $registro) {
                    fputcsv($salida, [
                        $registro->fecha->format('d/m/Y H:i:s'),
                        $registro->usuario?->nombre_usuario ?? '—',
                        $registro->etiqueta(),
                        $registro->entidad,
                        $registro->id_registro,
                        $registro->ip,
                        $registro->detalle ? json_encode($registro->detalle, JSON_UNESCAPED_UNICODE) : '',
                    ], ';');
                }
            });

            fclose($salida);
        }, 'auditoria_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtros(Request $request): array
    {
        return array_filter([
            'usuario' => filter_var($request->query('usuario'), FILTER_VALIDATE_INT) ?: null,
            'accion' => $request->query('accion'),
            'entidad' => $request->query('entidad'),
            'registro' => filter_var($request->query('registro'), FILTER_VALIDATE_INT) ?: null,
            'desde' => $this->fecha($request->query('desde')),
            'hasta' => $this->fecha($request->query('hasta')),
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function consulta(array $filtros): Builder
    {
        return Auditoria::query()
            ->when($filtros['usuario'] ?? null, fn (Builder $q, $id) => $q->where('id_usuario', $id))
            ->when($filtros['accion'] ?? null, fn (Builder $q, $accion) => $q->where('accion', $accion))
            ->when($filtros['entidad'] ?? null, fn (Builder $q, $entidad) => $q->where('entidad', $entidad))
            ->when($filtros['registro'] ?? null, fn (Builder $q, $id) => $q->where('id_registro', $id))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $desde) => $q->where('fecha', '>=', $desde.' 00:00:00'))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $hasta) => $q->where('fecha', '<=', $hasta.' 23:59:59'))
            ->orderByDesc('fecha')
            ->orderByDesc('id_auditoria');
    }

    private function fecha(mixed $valor): ?string
    {
        return is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : null;
    }
}
