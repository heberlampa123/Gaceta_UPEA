<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 4.4.11 Entidad Seguimiento_Documento: recorrido de cada documento.
 * Cada registro nunca se modifica ni se elimina (solo inserciones).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimiento_documento', function (Blueprint $table) {
            $table->increments('id_seguimiento');
            $table->integer('id_documento');
            $table->integer('id_estado_anterior')->nullable();
            $table->integer('id_estado_nuevo');
            $table->integer('id_version')->nullable();
            $table->string('accion', 60);
            $table->string('comentario', 500)->nullable();
            $table->integer('id_usuario');
            $table->dateTime('fecha')->useCurrent();

            $table->foreign('id_documento')->references('id_documento')->on('documento')->restrictOnDelete();
            $table->foreign('id_estado_anterior')->references('id_estado')->on('estado_documento')->restrictOnDelete();
            $table->foreign('id_estado_nuevo')->references('id_estado')->on('estado_documento')->restrictOnDelete();
            $table->foreign('id_version')->references('id_version')->on('version_documento')->restrictOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuario')->restrictOnDelete();

            $table->index(['id_documento', 'fecha']);
            $table->index('id_usuario');
        });

        // Función reutilizable para las tablas de solo inserción (también la usa "auditoria").
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION impedir_modificacion() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Los registros de % no pueden modificarse ni eliminarse.', TG_TABLE_NAME
                    USING ERRCODE = 'restrict_violation';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_seguimiento_documento_solo_insercion
                BEFORE UPDATE OR DELETE ON seguimiento_documento
                FOR EACH ROW EXECUTE FUNCTION impedir_modificacion();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimiento_documento');
        DB::unprepared('DROP FUNCTION IF EXISTS impedir_modificacion()');
    }
};
