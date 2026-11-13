<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrección de la revisión de brechas (13/11/2026): un borrador registrado por error puede anularse
 * (documento.activo = false). Para que su número quede disponible, la unicidad de
 * (tipo documental, número, gestión) se aplica solo a los documentos activos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE documento DROP CONSTRAINT IF EXISTS documento_tipo_numero_gestion_unique');
        DB::statement('CREATE UNIQUE INDEX documento_tipo_numero_gestion_activo_unique ON documento (id_tipo_documental, numero, gestion) WHERE activo');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS documento_tipo_numero_gestion_activo_unique');
        DB::statement('ALTER TABLE documento ADD CONSTRAINT documento_tipo_numero_gestion_unique UNIQUE (id_tipo_documental, numero, gestion)');
    }
};
