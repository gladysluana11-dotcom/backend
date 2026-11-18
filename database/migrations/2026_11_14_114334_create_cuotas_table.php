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
        Schema::create('cuotas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('matricula_id');
            $table->unsignedBigInteger('alumno_id');
            $table->unsignedBigInteger('anio_escolar_id');
            $table->string('concepto', 20);
            $table->unsignedTinyInteger('numero');
            $table->string('descripcion');
            $table->decimal('monto_original', 10, 2);
            $table->date('vencimiento');
            $table->timestamp('anulada_en')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();

            $table->unique(
                ['colegio_id', 'matricula_id', 'concepto', 'numero'],
                'cuotas_colegio_matricula_concepto_numero_unique',
            );
            $table->unique(['colegio_id', 'id'], 'cuotas_colegio_id_id_unique');
            $table->index(['colegio_id', 'alumno_id', 'vencimiento'], 'cuotas_colegio_alumno_vencimiento_index');
            $table->index(['colegio_id', 'matricula_id', 'vencimiento'], 'cuotas_colegio_matricula_vencimiento_index');
            $table->index(['colegio_id', 'anio_escolar_id', 'vencimiento'], 'cuotas_colegio_anio_vencimiento_index');
            $table->foreign(['colegio_id', 'matricula_id'], 'cuotas_colegio_matricula_foreign')
                ->references(['colegio_id', 'id'])
                ->on('matriculas');
            $table->foreign(['colegio_id', 'alumno_id'], 'cuotas_colegio_alumno_foreign')
                ->references(['colegio_id', 'id'])
                ->on('alumnos');
            $table->foreign(['colegio_id', 'anio_escolar_id'], 'cuotas_colegio_anio_foreign')
                ->references(['colegio_id', 'id'])
                ->on('anios_escolares');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuotas');
    }
};
