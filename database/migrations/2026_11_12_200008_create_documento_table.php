<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 4.4.8 Entidad Documento: datos comunes a todos los tipos documentales.
 * Los datos propios de cada tipo van en "metadatos". El PDF se guarda en Version_Documento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento', function (Blueprint $table) {
            $table->increments('id_documento');
            $table->integer('id_tipo_documental');
            $table->string('numero', 30);
            $table->smallInteger('gestion');
            $table->date('fecha_emision');
            $table->string('titulo', 300);
            $table->text('descripcion')->nullable();
            $table->string('instancia_emisora', 150)->nullable();
            $table->jsonb('metadatos')->nullable();
            $table->integer('id_estado_actual');
            $table->integer('id_usuario_registro');
            $table->dateTime('fecha_registro')->useCurrent();
            $table->dateTime('fecha_actualizacion')->useCurrent();
            $table->dateTime('fecha_publicacion')->nullable();
            $table->boolean('activo')->default(true);

            $table->foreign('id_tipo_documental')->references('id_tipo_documental')->on('tipo_documental')->restrictOnDelete();
            $table->foreign('id_estado_actual')->references('id_estado')->on('estado_documento')->restrictOnDelete();
            $table->foreign('id_usuario_registro')->references('id_usuario')->on('usuario')->restrictOnDelete();

            // Unicidad del documento (4.4.15)
            $table->unique(['id_tipo_documental', 'numero', 'gestion'], 'documento_tipo_numero_gestion_unique');

            // Índices para los filtros del panel y del portal público
            $table->index('gestion');
            $table->index('fecha_emision');
            $table->index('id_estado_actual');
            $table->index('id_usuario_registro');
        });

        // Búsqueda por palabras clave (número, título y descripción) en español.
        DB::statement(<<<'SQL'
            CREATE INDEX documento_busqueda_idx ON documento USING GIN (
                to_tsvector('spanish', coalesce(numero, '') || ' ' || coalesce(titulo, '') || ' ' || coalesce(descripcion, ''))
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('documento');
    }
};
