<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\TipoDocumental;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Búsqueda de documentos en el portal público (RF-06, Ref. 28–30).
 * La usan la página del portal (primera carga) y la API JSON (filtros con Axios),
 * para que ambas muestren exactamente los mismos resultados.
 * Solo devuelve documentos publicados y activos.
 */
class ConsultaPublica
{
    public const POR_PAGINA = 10;

    public const ORDENES = [
        'recientes' => 'Más recientes',
        'antiguos' => 'Más antiguos',
        'numero' => 'Número',
    ];

    /**
     * Limpia los filtros recibidos: descarta valores vacíos o inválidos sin producir errores,
     * para que un enlace mal escrito muestre resultados en lugar de una página de error.
     *
     * @return array{q?: string, tipo?: int, gestion?: int, numero?: string, desde?: string, hasta?: string, instancia?: string, orden: string}
     */
    public function filtros(array $entrada): array
    {
        $texto = fn (string $clave, int $max) => mb_substr(trim((string) ($entrada[$clave] ?? '')), 0, $max);
        $fecha = function (string $clave) use ($entrada) {
            $valor = (string) ($entrada[$clave] ?? '');

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) && checkdate((int) substr($valor, 5, 2), (int) substr($valor, 8, 2), (int) substr($valor, 0, 4))
                ? $valor
                : null;
        };

        $filtros = array_filter([
            'q' => $texto('q', 100),
            'tipo' => filter_var($entrada['tipo'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
            'gestion' => filter_var($entrada['gestion'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]) ?: null,
            'numero' => $texto('numero', 30),
            'desde' => $fecha('desde'),
            'hasta' => $fecha('hasta'),
            'instancia' => $texto('instancia', 150),
        ], fn ($valor) => $valor !== null && $valor !== '');

        $filtros['orden'] = array_key_exists($entrada['orden'] ?? '', self::ORDENES) ? $entrada['orden'] : 'recientes';

        return $filtros;
    }

    public function buscar(array $filtros, int $pagina = 1): LengthAwarePaginator
    {
        return Documento::query()
            ->publicados()
            ->with('tipoDocumental')
            ->when($filtros['q'] ?? null, fn (Builder $q, string $texto) => $q->where(fn (Builder $w) => $w
                ->buscar($texto)
                ->orWhere('numero', 'ilike', '%'.$texto.'%')
                ->orWhere('titulo', 'ilike', '%'.$texto.'%')))
            ->when($filtros['tipo'] ?? null, fn (Builder $q, int $tipo) => $q->where('id_tipo_documental', $tipo))
            ->when($filtros['gestion'] ?? null, fn (Builder $q, int $gestion) => $q->where('gestion', $gestion))
            ->when($filtros['numero'] ?? null, fn (Builder $q, string $numero) => $q->where('numero', 'ilike', '%'.$numero.'%'))
            ->when($filtros['desde'] ?? null, fn (Builder $q, string $desde) => $q->whereDate('fecha_emision', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, string $hasta) => $q->whereDate('fecha_emision', '<=', $hasta))
            ->when($filtros['instancia'] ?? null, fn (Builder $q, string $instancia) => $q->where('instancia_emisora', $instancia))
            ->when($filtros['orden'] === 'antiguos', fn (Builder $q) => $q->orderBy('fecha_emision')->orderBy('numero'))
            ->when($filtros['orden'] === 'numero', fn (Builder $q) => $q->orderByDesc('gestion')->orderBy('numero'))
            ->when($filtros['orden'] === 'recientes', fn (Builder $q) => $q->orderByDesc('fecha_emision')->orderByDesc('numero'))
            ->paginate(self::POR_PAGINA, ['*'], 'page', max(1, $pagina))
            ->withQueryString();
    }

    /** Opciones de los filtros, calculadas solo sobre documentos publicados. */
    public function opciones(): array
    {
        $publicados = fn () => Documento::query()->publicados();

        return [
            'tipos' => TipoDocumental::whereIn('id_tipo_documental', $publicados()->select('id_tipo_documental'))->orderBy('nombre')->get(['id_tipo_documental', 'nombre']),
            'gestiones' => $publicados()->distinct()->orderByDesc('gestion')->pluck('gestion'),
            'instancias' => $publicados()->whereNotNull('instancia_emisora')->distinct()->orderBy('instancia_emisora')->pluck('instancia_emisora'),
            'ordenes' => self::ORDENES,
        ];
    }

    /** Texto legible de los filtros activos, para anunciar los resultados (accesibilidad). */
    public static function resumenFechas(array $filtros): ?string
    {
        if (! isset($filtros['desde']) && ! isset($filtros['hasta'])) {
            return null;
        }

        $f = fn (?string $v) => $v ? Carbon::parse($v)->format('d/m/Y') : '…';

        return 'Emitidos entre '.$f($filtros['desde'] ?? null).' y '.$f($filtros['hasta'] ?? null);
    }

    public function totalPublicados(): int
    {
        return Documento::query()->publicados()->count();
    }

    /** @return Collection<int, Documento> */
    public function ultimosPublicados(int $cantidad = 5): Collection
    {
        return Documento::query()->publicados()->with('tipoDocumental')->latest('fecha_publicacion')->limit($cantidad)->get();
    }
}
