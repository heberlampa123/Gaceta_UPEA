<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 4.4.6 Tabla intermedia Usuario_Rol (PK compuesta: evita asignaciones duplicadas). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_rol', function (Blueprint $table) {
            $table->integer('id_usuario');
            $table->integer('id_rol');
            $table->primary(['id_usuario', 'id_rol']);

            $table->foreign('id_usuario')->references('id_usuario')->on('usuario')->cascadeOnDelete();
            $table->foreign('id_rol')->references('id_rol')->on('rol')->cascadeOnDelete();
            $table->index('id_rol');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_rol');
    }
};
