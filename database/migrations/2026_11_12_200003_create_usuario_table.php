<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 4.4.3 Entidad Usuario: cuentas con acceso al panel administrativo.
 * No se eliminan físicamente: se desactivan (activo = false).
 *
 * Campos agregados al modelo del análisis (decisión 12/11/2026):
 *  - bloqueado_hasta: bloqueo temporal tras varios intentos fallidos.
 *  - debe_cambiar_contrasena: obliga a cambiar la contraseña asignada por el administrador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario', function (Blueprint $table) {
            $table->increments('id_usuario');
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('correo', 150)->unique();
            $table->string('nombre_usuario', 50)->unique();
            $table->string('contrasena_hash', 255);
            $table->string('cargo', 100)->nullable();
            $table->boolean('activo')->default(true);
            $table->integer('intentos_fallidos')->default(0);
            $table->dateTime('bloqueado_hasta')->nullable();
            $table->boolean('debe_cambiar_contrasena')->default(true);
            $table->dateTime('ultimo_acceso')->nullable();
            $table->dateTime('fecha_creacion')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario');
    }
};
