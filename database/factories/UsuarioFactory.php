<?php

namespace Database\Factories;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    public function definition(): array
    {
        return [
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName().' '.fake()->lastName(),
            'correo' => fake()->unique()->safeEmail(),
            'nombre_usuario' => fake()->unique()->bothify('usuario.####??'),
            'contrasena_hash' => 'password',
            'cargo' => fake()->jobTitle(),
            'activo' => true,
            'debe_cambiar_contrasena' => false,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(['activo' => false]);
    }

    /** Asigna un rol existente por su código (ADMIN, ENCARGADO, REVISOR). */
    public function conRol(string $codigoRol): static
    {
        return $this->afterCreating(function (Usuario $usuario) use ($codigoRol) {
            $usuario->roles()->attach(Rol::where('codigo', $codigoRol)->valueOrFail('id_rol'));
        });
    }
}
