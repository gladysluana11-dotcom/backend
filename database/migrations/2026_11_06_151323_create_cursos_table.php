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
        Schema::create('cursos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('nivel_id');
            $table->unsignedBigInteger('grado_id');
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['colegio_id', 'nivel_id', 'grado_id', 'nombre']);
            $table->unique(['colegio_id', 'id']);
            $table->foreign(['colegio_id', 'nivel_id'])
                ->references(['colegio_id', 'id'])
                ->on('niveles')
                ->restrictOnDelete();
            $table->foreign(['colegio_id', 'grado_id'])
                ->references(['colegio_id', 'id'])
                ->on('grados')
                ->restrictOnDelete();
            $table->index(['colegio_id', 'grado_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cursos');
    }
};
