<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('configuraciones_colegio', function (Blueprint $table): void {
            $table->unsignedTinyInteger('dias_alerta_cuotas')->default(5);
        });

        Schema::table('cuotas', function (Blueprint $table): void {
            $table->index(
                ['colegio_id', 'anulada_en', 'vencimiento', 'alumno_id'],
                'cuotas_colegio_anulacion_vencimiento_alumno_index',
            );
        });

        Schema::table('matriculas', function (Blueprint $table): void {
            $table->index(
                ['colegio_id', 'anio_escolar_id', 'estado', 'seccion_id'],
                'matriculas_colegio_anio_estado_seccion_index',
            );
        });

        Schema::table('matricula_documentos', function (Blueprint $table): void {
            $table->index(
                ['colegio_id', 'matricula_id', 'estado'],
                'matricula_documentos_colegio_matricula_estado_index',
            );
        });

        Schema::table('alumno_apoderado', function (Blueprint $table): void {
            $table->index(
                ['colegio_id', 'responsable_pago', 'alumno_id'],
                'alumno_apoderado_responsable_alumno_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alumno_apoderado', function (Blueprint $table): void {
            $table->dropIndex('alumno_apoderado_responsable_alumno_index');
        });

        Schema::table('matricula_documentos', function (Blueprint $table): void {
            $table->dropIndex('matricula_documentos_colegio_matricula_estado_index');
        });

        Schema::table('matriculas', function (Blueprint $table): void {
            $table->dropIndex('matriculas_colegio_anio_estado_seccion_index');
        });

        Schema::table('cuotas', function (Blueprint $table): void {
            $table->dropIndex('cuotas_colegio_anulacion_vencimiento_alumno_index');
        });

        Schema::table('configuraciones_colegio', function (Blueprint $table): void {
            $table->dropColumn('dias_alerta_cuotas');
        });
    }
};
