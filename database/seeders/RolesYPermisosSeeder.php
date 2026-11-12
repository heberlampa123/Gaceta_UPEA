<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

/**
 * Roles de la primera versión (4.4.4) y catálogo de permisos (4.4.5).
 * El sistema verifica los permisos por su código; los roles solo los agrupan.
 * Se puede ejecutar varias veces sin duplicar datos.
 */
class RolesYPermisosSeeder extends Seeder
{
    /** codigo => [modulo, descripcion] */
    public const PERMISOS = [
        'usuario.gestionar' => ['usuarios', 'Crear, modificar, desactivar y desbloquear cuentas de usuario.'],
        'rol.gestionar' => ['usuarios', 'Administrar roles y asignarles permisos.'],
        'tipo_documental.gestionar' => ['tipos documentales', 'Crear y administrar tipos documentales.'],
        'auditoria.ver' => ['auditoria', 'Consultar el registro de auditoría.'],
        'documento.ver' => ['documentos', 'Consultar los documentos registrados y su seguimiento.'],
        'documento.registrar' => ['documentos', 'Registrar nuevos documentos y adjuntar su PDF.'],
        'documento.editar' => ['documentos', 'Modificar datos de documentos y cargar nuevas versiones del PDF.'],
        'documento.enviar_revision' => ['documentos', 'Enviar documentos a revisión.'],
        'observacion.atender' => ['documentos', 'Atender las observaciones registradas por el revisor.'],
        'documento.revisar' => ['revision', 'Revisar documentos, registrar observaciones y devolverlos para corrección.'],
        'documento.aprobar' => ['revision', 'Autorizar documentos para su publicación.'],
        'documento.publicar' => ['revision', 'Publicar documentos aprobados en el portal público.'],
    ];

    /** codigo => [nombre, descripcion, permisos] */
    public const ROLES = [
        Rol::ADMIN => [
            'Administrador',
            'Gestiona usuarios, roles, permisos, tipos documentales y consulta la auditoría.',
            ['usuario.gestionar', 'rol.gestionar', 'tipo_documental.gestionar', 'auditoria.ver', 'documento.ver'],
        ],
        Rol::ENCARGADO => [
            'Encargado de documentos',
            'Registra documentos, adjunta archivos, realiza correcciones y consulta su estado.',
            ['documento.ver', 'documento.registrar', 'documento.editar', 'documento.enviar_revision', 'observacion.atender'],
        ],
        Rol::REVISOR => [
            'Revisor / Publicador',
            'Revisa documentos, los aprueba, los devuelve con observaciones y gestiona su publicación.',
            ['documento.ver', 'documento.revisar', 'documento.aprobar', 'documento.publicar'],
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISOS as $codigo => [$modulo, $descripcion]) {
            Permiso::updateOrCreate(['codigo' => $codigo], ['modulo' => $modulo, 'descripcion' => $descripcion]);
        }

        foreach (self::ROLES as $codigo => [$nombre, $descripcion, $permisos]) {
            $rol = Rol::updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'descripcion' => $descripcion, 'activo' => true],
            );

            $rol->permisos()->sync(Permiso::whereIn('codigo', $permisos)->pluck('id_permiso'));
        }
    }
}
