<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\RelacionDocumental;
use App\Models\TipoRelacionDocumental;
use App\Models\Usuario;
use App\Support\Auditor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Relaciones entre normas (13/11/2026): el documento nuevo (origen) abroga, deroga o modifica
 * a otro ya publicado (destino).
 *
 * Reglas:
 *  - Las relaciones se registran o quitan mientras el documento nuevo es editable (Borrador u Observado),
 *    de modo que el revisor las ve y las aprueba junto con el documento.
 *  - La norma afectada debe estar publicada, activa y vigente, y no ser posterior al documento nuevo.
 *  - Solo una relación por par de documentos.
 *  - El efecto (p. ej. que la abrogada desaparezca del portal) se produce solo al publicarse el
 *    documento nuevo: la vigencia se calcula, no se guarda (ver Documento::scopeVigentes).
 */
class RelacionesDocumentales
{
    public const MAX_CANDIDATOS = 10;

    public function agregar(Documento $origen, int $idDestino, int $idTipo, ?string $detalle, Usuario $usuario): RelacionDocumental
    {
        return DB::transaction(function () use ($origen, $idDestino, $idTipo, $detalle, $usuario) {
            $tipo = TipoRelacionDocumental::where('activo', true)->find($idTipo)
                ?? throw ValidationException::withMessages(['id_tipo_relacion' => 'Seleccione un tipo de relación válido.']);

            $destino = Documento::visiblesEnPortal()->with('tipoDocumental')->find($idDestino)
                ?? throw ValidationException::withMessages(['id_documento_destino' => 'Seleccione una norma publicada y vigente.']);

            $detalle = trim((string) $detalle) ?: null;
            $errores = [];

            if ($destino->id_documento === $origen->id_documento) {
                $errores['id_documento_destino'] = 'Un documento no puede relacionarse consigo mismo.';
            } elseif ($destino->fecha_emision->gt($origen->fecha_emision)) {
                $errores['id_documento_destino'] = "{$destino->nombreCorto()} es posterior a este documento; solo se pueden afectar normas anteriores.";
            } elseif ($origen->relacionesEmitidas()->where('id_documento_destino', $destino->id_documento)->exists()) {
                $errores['id_documento_destino'] = "Ya registró una relación con {$destino->nombreCorto()}.";
            }

            if ($tipo->requiere_detalle && $detalle === null) {
                $errores['detalle'] = "Para «{$tipo->nombre}» indique qué parte se afecta (ej.: Artículos 3 y 5).";
            }

            if ($errores !== []) {
                throw ValidationException::withMessages($errores);
            }

            $relacion = RelacionDocumental::create([
                'id_documento_origen' => $origen->id_documento,
                'id_documento_destino' => $destino->id_documento,
                'id_tipo_relacion' => $tipo->id_tipo_relacion,
                'detalle' => $detalle ? mb_substr($detalle, 0, 300) : null,
                'id_usuario_registro' => $usuario->id_usuario,
            ]);

            Auditor::creacion($relacion);

            return $relacion;
        });
    }

    public function quitar(RelacionDocumental $relacion): void
    {
        DB::transaction(function () use ($relacion) {
            Auditor::registrar(Auditoria::ELIMINAR, 'RelacionDocumental', $relacion->id_relacion, [
                'anterior' => $relacion->getAttributes(),
            ]);
            $relacion->delete();
        });
    }

    /**
     * Normas que el documento puede afectar: publicadas, vigentes, anteriores a él y aún no relacionadas.
     * Búsqueda por número o palabras del título (para el buscador del panel, con Axios).
     *
     * @return Collection<int, Documento>
     */
    public function candidatos(Documento $origen, string $texto): Collection
    {
        $texto = mb_substr(trim($texto), 0, 100);

        if (mb_strlen($texto) < 2) {
            return collect();
        }

        return Documento::visiblesEnPortal()
            ->with('tipoDocumental')
            ->whereKeyNot($origen->id_documento)
            ->whereDate('fecha_emision', '<=', $origen->fecha_emision)
            ->whereNotIn('id_documento', $origen->relacionesEmitidas()->select('id_documento_destino'))
            ->where(fn (Builder $q) => $q->buscar($texto)
                ->orWhere('numero', 'ilike', '%'.$texto.'%')
                ->orWhere('titulo', 'ilike', '%'.$texto.'%'))
            ->orderByDesc('fecha_emision')
            ->limit(self::MAX_CANDIDATOS)
            ->get();
    }
}
