<?php

namespace Database\Factories;

use App\Models\Documento;
use App\Models\EstadoDocumento;
use App\Models\TipoDocumental;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Resoluciones del HCU de prueba. Requiere los catálogos sembrados
 * (estados y tipo RES_HCU) por DatabaseSeeder.
 *
 * @extends Factory<Documento>
 */
class DocumentoFactory extends Factory
{
    protected $model = Documento::class;

    private const ASUNTOS = [
        'aprueba el Reglamento de Titulación de la carrera de',
        'autoriza la convocatoria a docentes de la carrera de',
        'aprueba el plan de estudios de la carrera de',
        'reconoce la designación del Director de la carrera de',
        'aprueba el calendario académico de la carrera de',
        'autoriza el curso de actualización de la carrera de',
    ];

    private const CARRERAS = [
        'Ingeniería de Sistemas', 'Derecho', 'Medicina', 'Ciencias de la Educación',
        'Contaduría Pública', 'Arquitectura', 'Odontología', 'Ingeniería Civil',
    ];

    public function definition(): array
    {
        $gestion = fake()->numberBetween(2022, 2026);
        $numero = sprintf('%03d/%d', fake()->unique()->numberBetween(1, 999), $gestion);

        return [
            'id_tipo_documental' => fn () => TipoDocumental::where('codigo', TipoDocumental::RES_HCU)->valueOrFail('id_tipo_documental'),
            'numero' => $numero,
            'gestion' => $gestion,
            'fecha_emision' => fake()->dateTimeBetween("{$gestion}-01-01", "{$gestion}-12-31")->format('Y-m-d'),
            'titulo' => 'Resolución que '.fake()->randomElement(self::ASUNTOS).' '.fake()->randomElement(self::CARRERAS),
            'descripcion' => fake()->paragraph(),
            'instancia_emisora' => 'Honorable Consejo Universitario',
            'metadatos' => null,
            'id_estado_actual' => fn () => EstadoDocumento::idPorCodigo(EstadoDocumento::BORRADOR),
            'id_usuario_registro' => Usuario::factory(),
            'activo' => true,
        ];
    }

    public function enEstado(string $codigoEstado): static
    {
        return $this->state(fn () => [
            'id_estado_actual' => EstadoDocumento::idPorCodigo($codigoEstado),
            'fecha_publicacion' => $codigoEstado === EstadoDocumento::PUBLICADO ? now() : null,
        ]);
    }

    public function publicado(): static
    {
        return $this->enEstado(EstadoDocumento::PUBLICADO);
    }
}
