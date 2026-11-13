<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Relaciones entre normas (13/11/2026): una resolución nueva puede abrogar, derogar o modificar
 * otra ya publicada. No es una versión: es otro documento con su propio número.
 *
 *  - tipo_relacion_documental: catálogo (Abroga, Deroga, Modifica…). Agregar o quitar un tipo
 *    es un cambio de datos (TipoRelacionDocumentalSeeder), no de código.
 *  - relacion_documental: "el documento ORIGEN (nuevo) <tipo> al documento DESTINO (anterior)".
 *
 * La vigencia NO se guarda: se calcula. Un documento deja de ser vigente cuando existe una relación
 * cuyo tipo "quita_vigencia" y cuyo documento origen está publicado y activo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_relacion_documental', function (Blueprint $table) {
            $table->increments('id_tipo_relacion');
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 60);            // desde el documento nuevo: "Abroga"
            $table->string('nombre_inverso', 80);    // desde el documento afectado: "Abrogada por"
            $table->string('descripcion', 300)->nullable();
            $table->boolean('quita_vigencia')->default(false);
            $table->boolean('requiere_detalle')->default(false);
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
        });

        Schema::create('relacion_documental', function (Blueprint $table) {
            $table->increments('id_relacion');
            $table->integer('id_documento_origen');
            $table->integer('id_documento_destino');
            $table->integer('id_tipo_relacion');
            $table->string('detalle', 300)->nullable(); // ej.: "Artículos 3 y 5"
            $table->integer('id_usuario_registro');
            $table->dateTime('fecha_registro')->useCurrent();

            $table->foreign('id_documento_origen')->references('id_documento')->on('documento')->restrictOnDelete();
            $table->foreign('id_documento_destino')->references('id_documento')->on('documento')->restrictOnDelete();
            $table->foreign('id_tipo_relacion')->references('id_tipo_relacion')->on('tipo_relacion_documental')->restrictOnDelete();
            $table->foreign('id_usuario_registro')->references('id_usuario')->on('usuario')->restrictOnDelete();

            // Una sola relación entre el mismo par de documentos.
            $table->unique(['id_documento_origen', 'id_documento_destino']);
            $table->index('id_documento_destino');
        });

        DB::statement('ALTER TABLE relacion_documental ADD CONSTRAINT relacion_documental_distintos_check CHECK (id_documento_origen <> id_documento_destino)');
    }

    public function down(): void
    {
        Schema::dropIfExists('relacion_documental');
        Schema::dropIfExists('tipo_relacion_documental');
    }
};
