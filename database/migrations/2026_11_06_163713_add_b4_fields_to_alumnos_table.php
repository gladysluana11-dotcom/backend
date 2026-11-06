<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $duplicado = DB::table('alumnos')
            ->select('colegio_id')
            ->whereNotNull('documento_identidad')
            ->groupBy('colegio_id', 'documento_identidad')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicado) {
            throw new RuntimeException(
                'Hay documentos duplicados en alumnos; corrígelos antes de aplicar la restricción por colegio.',
            );
        }

        Schema::table('alumnos', function (Blueprint $table): void {
            $table->string('tipo_documento')->nullable()->after('apellido');
            $table->string('foto_path')->nullable()->after('direccion');
            $table->unique(['colegio_id', 'documento_identidad'], 'alumnos_colegio_documento_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alumnos', function (Blueprint $table): void {
            $table->dropUnique('alumnos_colegio_documento_unique');
            $table->dropColumn(['tipo_documento', 'foto_path']);
        });
    }
};
