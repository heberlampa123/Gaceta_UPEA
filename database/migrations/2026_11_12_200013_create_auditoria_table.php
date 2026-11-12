<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 4.4.14 Entidad Auditoria: operaciones importantes realizadas por los usuarios.
 * Solo admite inserciones; sus registros no se modifican ni se eliminan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->bigIncrements('id_auditoria');
            $table->integer('id_usuario')->nullable(); // NULL en intentos fallidos de inicio de sesión
            $table->string('accion', 60);
            $table->string('entidad', 50)->nullable();
            $table->integer('id_registro')->nullable();
            $table->jsonb('detalle')->nullable();
            $table->string('ip', 45)->nullable();
            $table->dateTime('fecha')->useCurrent();

            $table->foreign('id_usuario')->references('id_usuario')->on('usuario')->restrictOnDelete();

            $table->index('id_usuario');
            $table->index(['entidad', 'id_registro']);
            $table->index('fecha');
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_auditoria_solo_insercion
                BEFORE UPDATE OR DELETE ON auditoria
                FOR EACH ROW EXECUTE FUNCTION impedir_modificacion();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
