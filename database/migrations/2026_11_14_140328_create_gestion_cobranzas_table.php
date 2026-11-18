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
        Schema::create('gestiones_cobranza', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('alumno_id');
            $table->unsignedBigInteger('usuario_id');
            $table->date('fecha');
            $table->string('medio_contacto', 20);
            $table->string('resultado', 100);
            $table->date('compromiso_pago_fecha')->nullable();
            $table->decimal('compromiso_monto', 10, 2)->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['colegio_id', 'alumno_id', 'fecha'], 'gestiones_cobranza_colegio_alumno_fecha_index');
            $table->foreign(['colegio_id', 'alumno_id'], 'gestiones_cobranza_colegio_alumno_foreign')
                ->references(['colegio_id', 'id'])->on('alumnos');
            $table->foreign(['colegio_id', 'usuario_id'], 'gestiones_cobranza_colegio_usuario_foreign')
                ->references(['colegio_id', 'id'])->on('usuarios');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gestiones_cobranza');
    }
};
