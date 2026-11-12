<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 4.4.9 Entidad Version_Documento: versiones de los archivos PDF.
 * Reglas (4.4.15): una sola versión vigente por documento y (id_documento, numero_version) único.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('version_documento', function (Blueprint $table) {
            $table->increments('id_version');
            $table->integer('id_documento');
            $table->integer('numero_version');
            $table->string('nombre_original', 255);
            $table->string('ruta_archivo', 500);
            $table->bigInteger('tamano_bytes');
            $table->char('hash_sha256', 64);
            $table->string('motivo_cambio', 255)->nullable();
            $table->boolean('es_vigente')->default(false);
            $table->integer('id_usuario_carga');
            $table->dateTime('fecha_carga')->useCurrent();

            $table->foreign('id_documento')->references('id_documento')->on('documento')->restrictOnDelete();
            $table->foreign('id_usuario_carga')->references('id_usuario')->on('usuario')->restrictOnDelete();

            $table->unique(['id_documento', 'numero_version']);
            $table->index('id_usuario_carga');
        });

        DB::statement('ALTER TABLE version_documento ADD CONSTRAINT version_documento_numero_version_check CHECK (numero_version >= 1)');
        DB::statement('ALTER TABLE version_documento ADD CONSTRAINT version_documento_tamano_check CHECK (tamano_bytes > 0)');

        // Una sola versión vigente por documento: índice único parcial (4.4.15).
        DB::statement('CREATE UNIQUE INDEX version_documento_una_vigente ON version_documento (id_documento) WHERE es_vigente');
    }

    public function down(): void
    {
        Schema::dropIfExists('version_documento');
    }
};
