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
        Schema::table('apoderados', function (Blueprint $table): void {
            $table->unique(['colegio_id', 'id'], 'apoderados_colegio_id_id_unique');
        });

        Schema::create('alumno_apoderado', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('alumno_id');
            $table->unsignedBigInteger('apoderado_id');
            $table->string('parentesco');
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->boolean('responsable_pago')->default(false);
            $table->timestamps();

            $table->unique(
                ['colegio_id', 'alumno_id', 'apoderado_id'],
                'alumno_apoderado_colegio_alumno_apoderado_unique',
            );
            $table->foreign(['colegio_id', 'alumno_id'])
                ->references(['colegio_id', 'id'])
                ->on('alumnos')
                ->cascadeOnDelete();
            $table->foreign(['colegio_id', 'apoderado_id'])
                ->references(['colegio_id', 'id'])
                ->on('apoderados')
                ->cascadeOnDelete();
        });

        DB::table('apoderados')->orderBy('id')->chunk(200, function ($apoderados): void {
            $vinculos = [];

            foreach ($apoderados as $apoderado) {
                $vinculos[] = [
                    'colegio_id' => $apoderado->colegio_id,
                    'alumno_id' => $apoderado->alumno_id,
                    'apoderado_id' => $apoderado->id,
                    'parentesco' => $apoderado->parentesco,
                    'telefono' => $apoderado->telefono,
                    'email' => $apoderado->email,
                    'responsable_pago' => $apoderado->responsable_pago,
                    'created_at' => $apoderado->created_at,
                    'updated_at' => $apoderado->updated_at,
                ];
            }

            if ($vinculos !== []) {
                DB::table('alumno_apoderado')->insert($vinculos);
            }
        });

        Schema::table('apoderados', function (Blueprint $table): void {
            $table->dropForeign('apoderados_colegio_id_alumno_id_foreign');
            $table->dropIndex('apoderados_colegio_id_alumno_id_index');
            $table->dropColumn([
                'alumno_id',
                'parentesco',
                'telefono',
                'email',
                'responsable_pago',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $sinVinculo = DB::table('apoderados')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('alumno_apoderado')
                    ->whereColumn('alumno_apoderado.colegio_id', 'apoderados.colegio_id')
                    ->whereColumn('alumno_apoderado.apoderado_id', 'apoderados.id');
            })
            ->exists();

        if ($sinVinculo) {
            throw new LogicException('No se puede revertir: existen apoderados reutilizables sin vínculo a alumno.');
        }

        Schema::table('apoderados', function (Blueprint $table): void {
            $table->unsignedBigInteger('alumno_id')->nullable();
            $table->string('parentesco')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->boolean('responsable_pago')->default(false);
        });

        DB::table('alumno_apoderado')
            ->orderBy('apoderado_id')
            ->orderBy('id')
            ->get()
            ->groupBy('apoderado_id')
            ->each(function ($vinculos, int|string $apoderadoId): void {
                foreach ($vinculos->values() as $indice => $vinculo) {
                    $datos = [
                        'colegio_id' => $vinculo->colegio_id,
                        'alumno_id' => $vinculo->alumno_id,
                        'parentesco' => $vinculo->parentesco,
                        'telefono' => $vinculo->telefono,
                        'email' => $vinculo->email,
                        'responsable_pago' => $vinculo->responsable_pago,
                    ];

                    if ($indice === 0) {
                        DB::table('apoderados')->where('id', $apoderadoId)->update($datos);

                        continue;
                    }

                    $apoderado = DB::table('apoderados')->where('id', $apoderadoId)->first();
                    DB::table('apoderados')->insert([
                        ...$datos,
                        'nombre' => $apoderado->nombre,
                        'apellido' => $apoderado->apellido,
                        'created_at' => $apoderado->created_at,
                        'updated_at' => $apoderado->updated_at,
                    ]);
                }
            });

        Schema::table('apoderados', function (Blueprint $table): void {
            $table->unsignedBigInteger('alumno_id')->nullable(false)->change();
            $table->string('parentesco')->nullable(false)->change();
            $table->foreign(['colegio_id', 'alumno_id'])
                ->references(['colegio_id', 'id'])
                ->on('alumnos')
                ->cascadeOnDelete();
            $table->index(['colegio_id', 'alumno_id']);
            $table->dropUnique('apoderados_colegio_id_id_unique');
        });

        Schema::dropIfExists('alumno_apoderado');
    }
};
