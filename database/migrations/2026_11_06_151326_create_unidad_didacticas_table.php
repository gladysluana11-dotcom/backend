<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create didactic units. */
    public function up(): void
    {
        Schema::create('unidades_didacticas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('periodo_id');
            $table->string('nombre');
            $table->unsignedSmallInteger('orden');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['colegio_id', 'periodo_id', 'nombre']);
            $table->foreign(['colegio_id', 'periodo_id'])
                ->references(['colegio_id', 'id'])
                ->on('periodos')
                ->restrictOnDelete();
            $table->index(['colegio_id', 'periodo_id', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unidades_didacticas');
    }
};
