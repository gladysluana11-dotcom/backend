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
        Schema::create('periodos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('anio_escolar_id');
            $table->string('nombre');
            $table->unsignedSmallInteger('orden');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['colegio_id', 'anio_escolar_id', 'nombre']);
            $table->unique(['colegio_id', 'id']);
            $table->foreign(['colegio_id', 'anio_escolar_id'])
                ->references(['colegio_id', 'id'])
                ->on('anios_escolares')
                ->restrictOnDelete();
            $table->index(['colegio_id', 'anio_escolar_id', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periodos');
    }
};
