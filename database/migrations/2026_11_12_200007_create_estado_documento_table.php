<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 4.4.10 Entidad Estado_Documento: estados del flujo, identificados por su código.
 * Regla (4.4.15): un estado no puede desactivarse mientras existan documentos en él.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estado_documento', function (Blueprint $table) {
            $table->increments('id_estado');
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 60);
            $table->string('descripcion', 255)->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('es_publico')->default(false);
            $table->boolean('activo')->default(true);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION estado_documento_validar_desactivacion() RETURNS trigger AS $$
            BEGIN
                IF OLD.activo AND NOT NEW.activo
                   AND EXISTS (SELECT 1 FROM documento WHERE id_estado_actual = OLD.id_estado) THEN
                    RAISE EXCEPTION 'No se puede desactivar el estado %: existen documentos en ese estado.', OLD.codigo
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_estado_documento_desactivacion
                BEFORE UPDATE OF activo ON estado_documento
                FOR EACH ROW EXECUTE FUNCTION estado_documento_validar_desactivacion();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('estado_documento');
        DB::unprepared('DROP FUNCTION IF EXISTS estado_documento_validar_desactivacion()');
    }
};
