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
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->unique(['colegio_id', 'id'], 'usuarios_colegio_id_id_unique');
        });
        Schema::table('matriculas', function (Blueprint $table): void {
            $table->unsignedBigInteger('anio_escolar_id')->nullable();
            $table->unsignedBigInteger('grado_id')->nullable();
            $table->unsignedBigInteger('seccion_id')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->text('motivo')->nullable();
            $table->date('fecha_retiro')->nullable();
            $table->text('exceso_vacantes_motivo')->nullable();
            $table->unsignedBigInteger('exceso_vacantes_autorizado_por_id')->nullable();
        });

        DB::table('matriculas')->orderBy('id')->get()->each(function ($matricula): void {
            $anio = DB::table('anios_escolares')
                ->where('colegio_id', $matricula->colegio_id)
                ->where('nombre', $matricula->anio_escolar)
                ->first();

            if ($anio === null) {
                return;
            }

            $grado = DB::table('grados')
                ->where('colegio_id', $matricula->colegio_id)
                ->where('nombre', $matricula->grado)
                ->first();

            if ($grado === null) {
                return;
            }

            $seccion = DB::table('secciones')
                ->where('colegio_id', $matricula->colegio_id)
                ->where('anio_escolar_id', $anio->id)
                ->where('grado_id', $grado->id)
                ->where('nombre', $matricula->seccion)
                ->first();

            DB::table('matriculas')->where('id', $matricula->id)->update([
                'anio_escolar_id' => $anio->id,
                'grado_id' => $grado->id,
                'seccion_id' => $seccion?->id,
            ]);
        });

        $duplicado = DB::table('matriculas')
            ->select('colegio_id', 'alumno_id', 'anio_escolar_id')
            ->whereNotNull('anio_escolar_id')
            ->whereIn('estado', ['pre_inscrito', 'pendiente_documentos', 'matriculado'])
            ->groupBy('colegio_id', 'alumno_id', 'anio_escolar_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicado) {
            throw new RuntimeException(
                'Hay matrículas vigentes duplicadas; resuélvelas antes de aplicar la restricción por año.',
            );
        }

        Schema::table('matriculas', function (Blueprint $table): void {
            $table->unsignedTinyInteger('vigente')
                ->nullable()
                ->storedAs(
                    "CASE WHEN estado IN ('pre_inscrito', 'pendiente_documentos', 'matriculado') THEN 1 ELSE NULL END",
                );
            $table->unique(
                ['colegio_id', 'alumno_id', 'anio_escolar_id', 'vigente'],
                'matriculas_vigente_por_alumno_anio_unique',
            );
            $table->unique(['colegio_id', 'id'], 'matriculas_colegio_id_id_unique');
            $table->foreign(['colegio_id', 'anio_escolar_id'], 'matriculas_colegio_anio_foreign')
                ->references(['colegio_id', 'id'])
                ->on('anios_escolares');
            $table->foreign(['colegio_id', 'grado_id'], 'matriculas_colegio_grado_foreign')
                ->references(['colegio_id', 'id'])
                ->on('grados');
            $table->foreign(['colegio_id', 'seccion_id'], 'matriculas_colegio_seccion_foreign')
                ->references(['colegio_id', 'id'])
                ->on('secciones');
            $table->foreign(['colegio_id', 'usuario_id'], 'matriculas_colegio_usuario_foreign')
                ->references(['colegio_id', 'id'])
                ->on('usuarios');
            $table->foreign(
                ['colegio_id', 'exceso_vacantes_autorizado_por_id'],
                'matriculas_colegio_exceso_usuario_foreign',
            )
                ->references(['colegio_id', 'id'])
                ->on('usuarios');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table): void {
            $table->dropForeign('matriculas_colegio_anio_foreign');
            $table->dropForeign('matriculas_colegio_grado_foreign');
            $table->dropForeign('matriculas_colegio_seccion_foreign');
            $table->dropForeign('matriculas_colegio_usuario_foreign');
            $table->dropForeign('matriculas_colegio_exceso_usuario_foreign');
            $table->dropUnique('matriculas_vigente_por_alumno_anio_unique');
            $table->dropUnique('matriculas_colegio_id_id_unique');
            $table->dropColumn([
                'anio_escolar_id',
                'grado_id',
                'seccion_id',
                'usuario_id',
                'motivo',
                'fecha_retiro',
                'exceso_vacantes_motivo',
                'exceso_vacantes_autorizado_por_id',
                'vigente',
            ]);
        });
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->dropUnique('usuarios_colegio_id_id_unique');
        });
    }
};
