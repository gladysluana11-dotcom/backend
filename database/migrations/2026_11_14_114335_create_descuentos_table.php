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
        Schema::create('descuentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('cuota_id');
            $table->string('tipo', 20);
            $table->string('modo', 20);
            $table->decimal('valor', 10, 2);
            $table->decimal('monto_calculado', 10, 2);
            $table->text('motivo');
            $table->unsignedBigInteger('usuario_id');
            $table->timestamp('anulada_en')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->unsignedBigInteger('anulado_por_usuario_id')->nullable();
            $table->timestamps();

            $table->index(['colegio_id', 'cuota_id', 'anulada_en'], 'descuentos_colegio_cuota_anulacion_index');
            $table->foreign(['colegio_id', 'cuota_id'], 'descuentos_colegio_cuota_foreign')
                ->references(['colegio_id', 'id'])
                ->on('cuotas');
            $table->foreign(['colegio_id', 'usuario_id'], 'descuentos_colegio_usuario_foreign')
                ->references(['colegio_id', 'id'])
                ->on('usuarios');
            $table->foreign(['colegio_id', 'anulado_por_usuario_id'], 'descuentos_colegio_anulador_foreign')
                ->references(['colegio_id', 'id'])
                ->on('usuarios');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('descuentos');
    }
};
