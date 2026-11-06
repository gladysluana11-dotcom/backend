<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Link existing sections to their school year. */
    public function up(): void
    {
        Schema::table('secciones', function (Blueprint $table): void {
            $table->unsignedBigInteger('anio_escolar_id')->nullable()->after('colegio_id');
        });

        $colegios = DB::table('secciones')->distinct()->pluck('colegio_id');

        foreach ($colegios as $colegioId) {
            $anioEscolarId = DB::table('anios_escolares')
                ->where('colegio_id', $colegioId)
                ->orderByDesc('activo')
                ->orderByDesc('id')
                ->value('id');

            if ($anioEscolarId === null) {
                throw new RuntimeException("El colegio {$colegioId} tiene secciones sin año escolar.");
            }

            DB::table('secciones')
                ->where('colegio_id', $colegioId)
                ->whereNull('anio_escolar_id')
                ->update(['anio_escolar_id' => $anioEscolarId]);
        }

        Schema::table('secciones', function (Blueprint $table): void {
            $table->unsignedBigInteger('anio_escolar_id')->nullable(false)->change();
            $table->dropUnique(['colegio_id', 'grado_id', 'nombre']);
            $table->unique(['colegio_id', 'anio_escolar_id', 'grado_id', 'nombre'], 'seccion_unica_anio_grado');
            $table->unique(['colegio_id', 'id']);
            $table->foreign(['colegio_id', 'anio_escolar_id'])
                ->references(['colegio_id', 'id'])
                ->on('anios_escolares')
                ->restrictOnDelete();
            $table->index(['colegio_id', 'anio_escolar_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('secciones', function (Blueprint $table): void {
            $table->dropForeign(['colegio_id', 'anio_escolar_id']);
            $table->dropUnique(['colegio_id', 'id']);
            $table->dropUnique('seccion_unica_anio_grado');
            $table->dropIndex(['colegio_id', 'anio_escolar_id']);
            $table->unique(['colegio_id', 'grado_id', 'nombre']);
            $table->dropColumn('anio_escolar_id');
        });
    }
};
