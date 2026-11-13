<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rendimiento de la búsqueda (RNF-02, Fase 8).
 *
 * El portal y el panel buscan también por fragmentos del número y del título (ILIKE '%texto%'),
 * que no pueden usar índices normales. Los índices de trigramas (extensión pg_trgm) permiten que
 * PostgreSQL resuelva esas búsquedas con índices en lugar de recorrer toda la tabla.
 *
 * En producción, crear la extensión puede requerir un usuario con permisos de administrador de
 * PostgreSQL: CREATE EXTENSION IF NOT EXISTS pg_trgm;
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS documento_numero_trgm_idx ON documento USING GIN (numero gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS documento_titulo_trgm_idx ON documento USING GIN (titulo gin_trgm_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS documento_titulo_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS documento_numero_trgm_idx');
    }
};
