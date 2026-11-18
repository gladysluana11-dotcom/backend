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
        Schema::create('cierres_caja', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('usuario_id');
            $table->date('fecha');
            $table->text('observacion')->nullable();
            $table->decimal('total', 10, 2)->default(0);
            $table->json('resumen');
            $table->timestamps();

            $table->unique(['colegio_id', 'usuario_id', 'fecha'], 'cierres_caja_colegio_usuario_fecha_unique');
            $table->foreign(['colegio_id', 'usuario_id'], 'cierres_caja_colegio_usuario_foreign')
                ->references(['colegio_id', 'id'])->on('usuarios');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cierres_caja');
    }
};
