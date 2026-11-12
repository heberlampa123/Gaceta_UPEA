<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 4.4.13 Entidad Notificacion: avisos internos a los usuarios.
 * Una notificación sin fecha_lectura se considera no leída.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacion', function (Blueprint $table) {
            $table->increments('id_notificacion');
            $table->integer('id_usuario_destino');
            $table->integer('id_documento')->nullable();
            $table->string('tipo', 40);
            $table->string('titulo', 150);
            $table->string('mensaje', 500);
            $table->dateTime('fecha_creacion')->useCurrent();
            $table->dateTime('fecha_lectura')->nullable();

            $table->foreign('id_usuario_destino')->references('id_usuario')->on('usuario')->restrictOnDelete();
            $table->foreign('id_documento')->references('id_documento')->on('documento')->restrictOnDelete();

            $table->index(['id_usuario_destino', 'fecha_lectura']);
            $table->index('id_documento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacion');
    }
};
