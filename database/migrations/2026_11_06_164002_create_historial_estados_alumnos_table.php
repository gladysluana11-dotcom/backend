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
        Schema::create('historial_estados_alumnos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('alumno_id');
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');
            $table->text('motivo')->nullable();
            $table->dateTime('fecha');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->foreign(['colegio_id', 'alumno_id'])
                ->references(['colegio_id', 'id'])
                ->on('alumnos')
                ->cascadeOnDelete();
            $table->foreign(['colegio_id', 'usuario_id'])
                ->references(['colegio_id', 'id'])
                ->on('usuarios');
            $table->index(['colegio_id', 'alumno_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_estados_alumnos');
    }
};
