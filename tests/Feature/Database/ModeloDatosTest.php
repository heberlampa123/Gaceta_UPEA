<?php

namespace Tests\Feature\Database;

use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\EstadoDocumento;
use App\Models\Observacion;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\SeguimientoDocumento;
use App\Models\TipoDocumental;
use App\Models\Usuario;
use App\Models\VersionDocumento;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifica que la base de datos cumpla el modelo de la sección 4.4
 * y las restricciones de integridad de la sección 4.4.15 del análisis.
 */
class ModeloDatosTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    // ------------------------------------------------------------ Datos iniciales

    public function test_los_seeders_crean_los_catalogos_iniciales(): void
    {
        $this->assertEqualsCanonicalizing(
            [Rol::ADMIN, Rol::ENCARGADO, Rol::REVISOR],
            Rol::pluck('codigo')->all(),
        );

        $this->assertSame(
            ['BORRADOR', 'PENDIENTE_REVISION', 'OBSERVADO', 'APROBADO', 'PUBLICADO'],
            EstadoDocumento::orderBy('orden')->pluck('codigo')->all(),
        );

        $this->assertSame(['PUBLICADO'], EstadoDocumento::where('es_publico', true)->pluck('codigo')->all());
        $this->assertTrue(TipoDocumental::where('codigo', TipoDocumental::RES_HCU)->exists());
        $this->assertGreaterThan(0, Permiso::count());

        $admin = Usuario::where('nombre_usuario', config('gaceta.admin.nombre_usuario'))->firstOrFail();
        $this->assertTrue($admin->tieneRol(Rol::ADMIN));
        $this->assertTrue($admin->debe_cambiar_contrasena);
    }

    // ------------------------------------------------------------ Seguridad

    public function test_los_permisos_se_verifican_por_codigo_segun_el_rol(): void
    {
        $encargado = Usuario::factory()->conRol(Rol::ENCARGADO)->create();
        $revisor = Usuario::factory()->conRol(Rol::REVISOR)->create();
        $admin = Usuario::factory()->conRol(Rol::ADMIN)->create();

        $this->assertTrue($encargado->tienePermiso('documento.registrar'));
        $this->assertFalse($encargado->tienePermiso('documento.publicar'));

        $this->assertTrue($revisor->tienePermiso('documento.publicar'));
        $this->assertFalse($revisor->tienePermiso('documento.registrar'));

        $this->assertTrue($admin->tienePermiso('usuario.gestionar'));
        $this->assertFalse($admin->tienePermiso('documento.publicar'));
    }

    public function test_un_usuario_inactivo_o_con_rol_inactivo_no_tiene_permisos(): void
    {
        $inactivo = Usuario::factory()->inactivo()->conRol(Rol::REVISOR)->create();
        $this->assertFalse($inactivo->tienePermiso('documento.publicar'));

        $revisor = Usuario::factory()->conRol(Rol::REVISOR)->create();
        Rol::where('codigo', Rol::REVISOR)->update(['activo' => false]);
        $revisor->olvidarPermisos();

        $this->assertFalse($revisor->tienePermiso('documento.publicar'));
    }

    public function test_la_contrasena_se_guarda_cifrada_y_permite_iniciar_sesion(): void
    {
        $usuario = Usuario::factory()->create([
            'nombre_usuario' => 'jperez',
            'contrasena_hash' => 'Secreta2026',
        ]);

        $this->assertNotSame('Secreta2026', $usuario->getRawOriginal('contrasena_hash'));
        $this->assertStringStartsWith('$2y$', $usuario->getRawOriginal('contrasena_hash'));

        $this->assertTrue(Auth::attempt(['nombre_usuario' => 'jperez', 'password' => 'Secreta2026']));
        $this->assertFalse(Auth::attempt(['nombre_usuario' => 'jperez', 'password' => 'incorrecta']));
    }

    public function test_el_bloqueo_temporal_expira(): void
    {
        $usuario = Usuario::factory()->create(['bloqueado_hasta' => null]);
        $this->assertFalse($usuario->estaBloqueado());

        $usuario->forceFill(['bloqueado_hasta' => now()->addMinutes(Usuario::MINUTOS_BLOQUEO)])->save();
        $this->assertTrue($usuario->estaBloqueado());

        $this->travel(Usuario::MINUTOS_BLOQUEO + 1)->minutes();
        $this->assertFalse($usuario->estaBloqueado());
    }

    // ------------------------------------------------------------ Restricciones de integridad (4.4.15)

    public function test_no_pueden_existir_dos_documentos_con_el_mismo_tipo_numero_y_gestion(): void
    {
        Documento::factory()->create(['numero' => '025/2026', 'gestion' => 2026]);

        // Mismo número en otra gestión: permitido.
        Documento::factory()->create(['numero' => '025/2026', 'gestion' => 2025]);

        $this->assertViolaRestriccion(
            fn () => Documento::factory()->create(['numero' => '025/2026', 'gestion' => 2026]),
        );
    }

    public function test_cada_documento_tiene_como_maximo_una_version_vigente(): void
    {
        $documento = Documento::factory()->create();

        $this->crearVersion($documento, 1, vigente: false);
        $this->crearVersion($documento, 2, vigente: true);

        $this->assertViolaRestriccion(fn () => $this->crearVersion($documento, 3, vigente: true));

        // Número de versión repetido dentro del mismo documento.
        $this->assertViolaRestriccion(fn () => $this->crearVersion($documento, 2, vigente: false));

        // Otro documento sí puede tener su propia versión vigente.
        $this->crearVersion(Documento::factory()->create(), 1, vigente: true);

        $this->assertSame(2, $documento->versionVigente()->value('numero_version'));
    }

    public function test_una_observacion_atendida_debe_registrar_respuesta_usuario_y_fecha(): void
    {
        $documento = Documento::factory()->create();
        $version = $this->crearVersion($documento, 1, vigente: true);
        $revisor = Usuario::factory()->conRol(Rol::REVISOR)->create();

        $observacion = Observacion::create([
            'id_version' => $version->id_version,
            'id_usuario_revisor' => $revisor->id_usuario,
            'descripcion' => 'El número de resolución no coincide con el PDF.',
        ]);

        $this->assertFalse($observacion->estaAtendida());
        $this->assertSame(1, $documento->observaciones()->pendientes()->count());

        // Solo la respuesta, sin usuario ni fecha: rechazado.
        $this->assertViolaRestriccion(fn () => $observacion->update(['respuesta' => 'Corregido.']));

        $observacion->refresh()->update([
            'respuesta' => 'Se corrigió el número.',
            'id_usuario_atiende' => $documento->id_usuario_registro,
            'fecha_atencion' => now(),
        ]);

        $this->assertTrue($observacion->estaAtendida());
        $this->assertSame(0, $documento->observaciones()->pendientes()->count());
    }

    public function test_el_seguimiento_y_la_auditoria_solo_admiten_inserciones(): void
    {
        $documento = Documento::factory()->create();

        $seguimiento = SeguimientoDocumento::create([
            'id_documento' => $documento->id_documento,
            'id_estado_anterior' => null,
            'id_estado_nuevo' => $documento->id_estado_actual,
            'accion' => SeguimientoDocumento::ACCION_REGISTRO,
            'id_usuario' => $documento->id_usuario_registro,
        ]);

        $auditoria = Auditoria::create([
            'id_usuario' => null, // intento fallido de inicio de sesión
            'accion' => Auditoria::INICIO_SESION_FALLIDO,
            'entidad' => 'Usuario',
            'detalle' => ['nombre_usuario' => 'desconocido'],
            'ip' => '127.0.0.1',
        ]);

        $this->assertViolaRestriccion(fn () => $seguimiento->update(['comentario' => 'cambiado']));
        $this->assertViolaRestriccion(fn () => $seguimiento->delete());
        $this->assertViolaRestriccion(fn () => $auditoria->update(['accion' => 'OTRA']));
        $this->assertViolaRestriccion(fn () => $auditoria->delete());

        $this->assertSame(1, SeguimientoDocumento::count());
        $this->assertSame(1, Auditoria::count());
    }

    public function test_un_estado_con_documentos_no_puede_desactivarse(): void
    {
        Documento::factory()->enEstado(EstadoDocumento::OBSERVADO)->create();

        $this->assertViolaRestriccion(
            fn () => EstadoDocumento::where('codigo', EstadoDocumento::OBSERVADO)->update(['activo' => false]),
        );

        // Un estado sin documentos sí puede desactivarse.
        EstadoDocumento::where('codigo', EstadoDocumento::APROBADO)->update(['activo' => false]);
        $this->assertFalse(EstadoDocumento::where('codigo', EstadoDocumento::APROBADO)->value('activo'));
    }

    // ------------------------------------------------------------ Consultas

    public function test_el_portal_solo_ve_documentos_publicados_y_activos(): void
    {
        Documento::factory(2)->publicado()->create();
        Documento::factory()->publicado()->create(['activo' => false]);
        Documento::factory()->enEstado(EstadoDocumento::APROBADO)->create();
        Documento::factory()->create(); // borrador

        $this->assertSame(2, Documento::publicados()->count());
    }

    public function test_la_busqueda_por_palabras_clave_usa_numero_titulo_y_descripcion(): void
    {
        Documento::factory()->publicado()->create([
            'numero' => '101/2026',
            'titulo' => 'Resolución que aprueba el Reglamento de Titulación',
            'descripcion' => 'Modalidades de graduación de la carrera de Ingeniería de Sistemas.',
        ]);
        Documento::factory()->publicado()->create([
            'numero' => '102/2026',
            'titulo' => 'Resolución que autoriza la convocatoria docente',
            'descripcion' => 'Convocatoria para la carrera de Derecho.',
        ]);

        $this->assertSame(['101/2026'], Documento::buscar('reglamento')->pluck('numero')->all());
        $this->assertSame(['101/2026'], Documento::buscar('Graduación sistemas')->pluck('numero')->all());
        $this->assertSame(['102/2026'], Documento::buscar('convocatorias')->pluck('numero')->all()); // plural → raíz
        $this->assertSame(2, Documento::buscar('')->count());
    }

    // ------------------------------------------------------------ Utilidades

    private function crearVersion(Documento $documento, int $numero, bool $vigente): VersionDocumento
    {
        return VersionDocumento::create([
            'id_documento' => $documento->id_documento,
            'numero_version' => $numero,
            'nombre_original' => "resolucion_v{$numero}.pdf",
            'ruta_archivo' => "documentos/{$documento->id_documento}/v{$numero}.pdf",
            'tamano_bytes' => 1024,
            'hash_sha256' => hash('sha256', "v{$numero}"),
            'motivo_cambio' => $numero === 1 ? 'Versión inicial' : 'Corrección',
            'es_vigente' => $vigente,
            'id_usuario_carga' => $documento->id_usuario_registro,
        ]);
    }

    /**
     * Ejecuta la operación dentro de un punto de guardado (savepoint), de modo que
     * el error de PostgreSQL no invalide el resto de la prueba.
     */
    private function assertViolaRestriccion(callable $operacion): void
    {
        try {
            DB::transaction($operacion);
        } catch (QueryException $e) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Se esperaba que la base de datos rechazara la operación.');
    }
}
