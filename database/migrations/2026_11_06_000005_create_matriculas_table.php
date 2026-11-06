<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matriculas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('alumno_id');
            $table->string('anio_escolar');
            $table->string('grado');
            $table->string('seccion');
            $table->string('estado')->default('matriculado');
            $table->date('fecha_matricula');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign(['colegio_id', 'alumno_id'])
                ->references(['colegio_id', 'id'])
                ->on('alumnos')
                ->cascadeOnDelete();
            $table->index(['colegio_id', 'alumno_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matriculas');
    }
};
