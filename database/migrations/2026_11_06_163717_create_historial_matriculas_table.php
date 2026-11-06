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
        Schema::create('historial_matriculas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('matricula_id');
            $table->string('evento');
            $table->unsignedBigInteger('grado_anterior_id')->nullable();
            $table->unsignedBigInteger('seccion_anterior_id')->nullable();
            $table->unsignedBigInteger('grado_nuevo_id')->nullable();
            $table->unsignedBigInteger('seccion_nueva_id')->nullable();
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo')->nullable();
            $table->text('motivo')->nullable();
            $table->dateTime('fecha');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->foreign(['colegio_id', 'matricula_id'])
                ->references(['colegio_id', 'id'])
                ->on('matriculas')
                ->cascadeOnDelete();
            $table->foreign(['colegio_id', 'grado_anterior_id'])
                ->references(['colegio_id', 'id'])
                ->on('grados');
            $table->foreign(['colegio_id', 'seccion_anterior_id'])
                ->references(['colegio_id', 'id'])
                ->on('secciones');
            $table->foreign(['colegio_id', 'grado_nuevo_id'])
                ->references(['colegio_id', 'id'])
                ->on('grados');
            $table->foreign(['colegio_id', 'seccion_nueva_id'])
                ->references(['colegio_id', 'id'])
                ->on('secciones');
            $table->foreign(['colegio_id', 'usuario_id'])
                ->references(['colegio_id', 'id'])
                ->on('usuarios');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_matriculas');
    }
};
