<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create teacher-course-section assignments. */
    public function up(): void
    {
        Schema::create('asignaciones_docentes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('anio_escolar_id');
            $table->unsignedBigInteger('curso_id');
            $table->unsignedBigInteger('seccion_id');
            $table->foreignId('docente_id')->constrained('usuarios')->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(
                ['colegio_id', 'anio_escolar_id', 'curso_id', 'seccion_id'],
                'asignacion_unica_curso_seccion_anio',
            );
            $table->unique(['colegio_id', 'id']);
            $table->foreign(['colegio_id', 'anio_escolar_id'])
                ->references(['colegio_id', 'id'])
                ->on('anios_escolares')
                ->restrictOnDelete();
            $table->foreign(['colegio_id', 'curso_id'])
                ->references(['colegio_id', 'id'])
                ->on('cursos')
                ->restrictOnDelete();
            $table->foreign(['colegio_id', 'seccion_id'])
                ->references(['colegio_id', 'id'])
                ->on('secciones')
                ->restrictOnDelete();
            $table->index(
                ['colegio_id', 'docente_id', 'anio_escolar_id'],
                'asignacion_docente_por_usuario_anio',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaciones_docentes');
    }
};
