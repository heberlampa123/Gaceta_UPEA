<?php

namespace App\Console\Commands;

use App\Models\Usuario;
use Illuminate\Console\Command;

/**
 * Tokens de acceso a la API protegida (Sanctum) para clientes externos: Bruno, Postman u otros sistemas.
 * El panel no los necesita: la campana usa la sesión del navegador.
 *
 *   php artisan gaceta:token revisor                 → crea un token "bruno" válido por 30 días
 *   php artisan gaceta:token revisor --nombre=sigei --dias=90
 *   php artisan gaceta:token revisor --revocar       → elimina todos los tokens del usuario
 *
 * El token se muestra una sola vez (la base de datos guarda solo su huella).
 * Al restablecer la contraseña de un usuario, el sistema revoca sus tokens.
 */
class TokenApi extends Command
{
    protected $signature = 'gaceta:token
        {usuario : Nombre de usuario o correo}
        {--nombre=bruno : Nombre para identificar el token}
        {--dias=30 : Días de validez}
        {--revocar : Elimina todos los tokens del usuario}';

    protected $description = 'Crea o revoca tokens de acceso a la API para un usuario';

    public function handle(): int
    {
        $dato = mb_strtolower(trim($this->argument('usuario')));
        $usuario = Usuario::whereRaw('lower(nombre_usuario) = ?', [$dato])->orWhereRaw('lower(correo) = ?', [$dato])->first();

        if (! $usuario) {
            $this->components->error("No existe el usuario «{$this->argument('usuario')}».");

            return self::FAILURE;
        }

        if ($this->option('revocar')) {
            $cantidad = $usuario->tokens()->delete();
            $this->components->info("Se revocaron {$cantidad} token(s) de {$usuario->nombre_usuario}.");

            return self::SUCCESS;
        }

        if (! $usuario->activo) {
            $this->components->error('La cuenta está desactivada.');

            return self::FAILURE;
        }

        $dias = max(1, (int) $this->option('dias'));
        $token = $usuario->createToken($this->option('nombre'), ['*'], now()->addDays($dias));

        $this->components->info("Token «{$this->option('nombre')}» para {$usuario->nombre_usuario}, válido hasta el ".now()->addDays($dias)->format('d/m/Y').'.');
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->components->warn('Cópielo ahora: no se volverá a mostrar.');

        if ($usuario->debe_cambiar_contrasena) {
            $this->components->warn('Este usuario debe cambiar su contraseña: la API le responderá 403 hasta que lo haga.');
        }

        return self::SUCCESS;
    }
}
