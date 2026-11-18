<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('matricula_id');
            $table->unsignedBigInteger('alumno_id');
            $table->unsignedBigInteger('usuario_id');
            $table->date('fecha_pago');
            $table->decimal('monto', 10, 2);
            $table->string('medio', 30);
            $table->string('numero_operacion')->nullable();
            $table->text('observacion')->nullable();
            $table->string('estado', 20)->default('vigente');
            $table->string('serie_constancia', 20);
            $table->unsignedBigInteger('numero_constancia');
            $table->string('constancia', 40);
            $table->timestamp('anulada_en')->nullable();
            $table->unsignedBigInteger('anulado_por_usuario_id')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->string('voucher_path')->nullable();
            $table->timestamps();

            $table->unique(['colegio_id', 'id'], 'pagos_colegio_id_id_unique');
            $table->unique(['colegio_id', 'constancia'], 'pagos_colegio_constancia_unique');
            $table->unique(
                ['colegio_id', 'serie_constancia', 'numero_constancia'],
                'pagos_colegio_serie_numero_unique',
            );
            $table->index(['colegio_id', 'fecha_pago', 'estado'], 'pagos_colegio_fecha_estado_index');
            $table->index(['colegio_id', 'alumno_id', 'fecha_pago'], 'pagos_colegio_alumno_fecha_index');
            $table->index(['colegio_id', 'usuario_id', 'fecha_pago'], 'pagos_colegio_usuario_fecha_index');
            $table->index(['colegio_id', 'medio', 'fecha_pago'], 'pagos_colegio_medio_fecha_index');
            $table->foreign(['colegio_id', 'matricula_id'], 'pagos_colegio_matricula_foreign')
                ->references(['colegio_id', 'id'])->on('matriculas');
            $table->foreign(['colegio_id', 'alumno_id'], 'pagos_colegio_alumno_foreign')
                ->references(['colegio_id', 'id'])->on('alumnos');
            $table->foreign(['colegio_id', 'usuario_id'], 'pagos_colegio_usuario_foreign')
                ->references(['colegio_id', 'id'])->on('usuarios');
            $table->foreign(['colegio_id', 'anulado_por_usuario_id'], 'pagos_colegio_anulador_foreign')
                ->references(['colegio_id', 'id'])->on('usuarios');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
