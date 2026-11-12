<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 4.4.6 Tabla intermedia Rol_Permiso (PK compuesta: evita asignaciones duplicadas). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rol_permiso', function (Blueprint $table) {
            $table->integer('id_rol');
            $table->integer('id_permiso');
            $table->primary(['id_rol', 'id_permiso']);

            $table->foreign('id_rol')->references('id_rol')->on('rol')->cascadeOnDelete();
            $table->foreign('id_permiso')->references('id_permiso')->on('permiso')->cascadeOnDelete();
            $table->index('id_permiso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_permiso');
    }
};
