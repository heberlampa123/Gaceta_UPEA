<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 4.4.12 Entidad Observacion: observaciones del Revisor sobre una versión concreta.
 * Regla (4.4.15): respuesta, id_usuario_atiende y fecha_atencion son todos NULL
 * (pendiente) o todos tienen valor (atendida).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observacion', function (Blueprint $table) {
            $table->increments('id_observacion');
            $table->integer('id_version');
            $table->integer('id_usuario_revisor');
            $table->text('descripcion');
            $table->text('respuesta')->nullable();
            $table->integer('id_usuario_atiende')->nullable();
            $table->dateTime('fecha_registro')->useCurrent();
            $table->dateTime('fecha_atencion')->nullable();

            $table->foreign('id_version')->references('id_version')->on('version_documento')->restrictOnDelete();
            $table->foreign('id_usuario_revisor')->references('id_usuario')->on('usuario')->restrictOnDelete();
            $table->foreign('id_usuario_atiende')->references('id_usuario')->on('usuario')->restrictOnDelete();

            $table->index('id_version');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE observacion ADD CONSTRAINT observacion_atencion_completa_check CHECK (
                (respuesta IS NULL AND id_usuario_atiende IS NULL AND fecha_atencion IS NULL)
                OR (respuesta IS NOT NULL AND id_usuario_atiende IS NOT NULL AND fecha_atencion IS NOT NULL)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('observacion');
    }
};
