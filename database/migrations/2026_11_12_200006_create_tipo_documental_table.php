<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 4.4.7 Entidad Tipo_Documental: clasificación de los documentos (p. ej. RES_HCU). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_documental', function (Blueprint $table) {
            $table->increments('id_tipo_documental');
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 100);
            $table->string('descripcion', 255)->nullable();
            // Definición de los campos propios del tipo: [{nombre, etiqueta, tipo, obligatorio}]
            $table->jsonb('campos_adicionales')->nullable();
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_documental');
    }
};
