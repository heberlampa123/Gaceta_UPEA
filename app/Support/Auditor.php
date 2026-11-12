<?php

namespace App\Support;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Registra operaciones importantes en la tabla "auditoria" (RF-09, 4.4.14):
 * quién realizó la acción, cuándo, desde qué IP y qué datos cambiaron.
 */
class Auditor
{
    /** Atributos que nunca se guardan en el detalle de auditoría. */
    private const OCULTOS = ['contrasena_hash'];

    public static function registrar(
        string $accion,
        ?string $entidad = null,
        ?int $idRegistro = null,
        ?array $detalle = null,
        ?int $idUsuario = null,
    ): Auditoria {
        return Auditoria::create([
            'id_usuario' => $idUsuario ?? Auth::id(),
            'accion' => $accion,
            'entidad' => $entidad,
            'id_registro' => $idRegistro,
            'detalle' => $detalle,
            'ip' => request()?->ip(),
        ]);
    }

    /** Registra la creación de un modelo con sus valores iniciales. */
    public static function creacion(Model $modelo, array $extra = []): Auditoria
    {
        return self::registrar(
            Auditoria::CREAR,
            class_basename($modelo),
            $modelo->getKey(),
            ['nuevo' => self::limpiar($modelo->getAttributes())] + $extra,
        );
    }

    /**
     * Registra una modificación con los valores anteriores y nuevos.
     * Debe llamarse después de save(), cuando getChanges() tiene los cambios.
     */
    public static function modificacion(Model $modelo, array $extra = [], string $accion = Auditoria::MODIFICAR): ?Auditoria
    {
        $cambios = self::limpiar(array_diff_key($modelo->getChanges(), array_flip(self::marcasDeTiempo($modelo))));

        if ($cambios === [] && $extra === []) {
            return null;
        }

        $anterior = array_intersect_key(self::limpiar($modelo->getPrevious()), $cambios);

        return self::registrar(
            $accion,
            class_basename($modelo),
            $modelo->getKey(),
            array_filter(['anterior' => $anterior, 'nuevo' => $cambios]) + $extra,
        );
    }

    private static function limpiar(array $atributos): array
    {
        return array_diff_key($atributos, array_flip(self::OCULTOS));
    }

    private static function marcasDeTiempo(Model $modelo): array
    {
        return array_filter([$modelo->getCreatedAtColumn(), $modelo->getUpdatedAtColumn()]);
    }
}
