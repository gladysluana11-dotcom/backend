<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asignaciones_secciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('anio_escolar_id');
            $table->unsignedBigInteger('seccion_id');
            $table->unsignedBigInteger('usuario_id');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(
                ['colegio_id', 'anio_escolar_id', 'seccion_id', 'usuario_id'],
                'asignaciones_secciones_usuario_unico',
            );
            $table->foreign(['colegio_id', 'anio_escolar_id'])
                ->references(['colegio_id', 'id'])
                ->on('anios_escolares')
                ->cascadeOnDelete();
            $table->foreign(['colegio_id', 'seccion_id'])
                ->references(['colegio_id', 'id'])
                ->on('secciones')
                ->cascadeOnDelete();
            $table->foreign(['colegio_id', 'usuario_id'])
                ->references(['colegio_id', 'id'])
                ->on('usuarios')
                ->cascadeOnDelete();
        });

        DB::table('asignaciones_docentes')
            ->where('activo', true)
            ->orderBy('id')
            ->get()
            ->each(function ($asignacion): void {
                DB::table('asignaciones_secciones')->insertOrIgnore([
                    'colegio_id' => $asignacion->colegio_id,
                    'anio_escolar_id' => $asignacion->anio_escolar_id,
                    'seccion_id' => $asignacion->seccion_id,
                    'usuario_id' => $asignacion->docente_id,
                    'activo' => true,
                    'created_at' => $asignacion->created_at,
                    'updated_at' => $asignacion->updated_at,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaciones_secciones');
    }
};
