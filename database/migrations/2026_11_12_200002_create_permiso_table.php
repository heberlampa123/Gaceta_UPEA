<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 4.4.5 Entidad Permiso: acciones que pueden autorizarse (se verifican por código). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permiso', function (Blueprint $table) {
            $table->increments('id_permiso');
            $table->string('codigo', 80)->unique();
            $table->string('modulo', 50)->index();
            $table->string('descripcion', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permiso');
    }
};
