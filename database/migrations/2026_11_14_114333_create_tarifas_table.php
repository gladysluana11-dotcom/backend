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
        Schema::create('tarifas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('anio_escolar_id');
            $table->unsignedBigInteger('grado_id');
            $table->decimal('monto_matricula', 10, 2);
            $table->decimal('monto_pension', 10, 2);
            $table->unsignedTinyInteger('numero_cuotas');
            $table->unsignedTinyInteger('dia_vencimiento');
            $table->unsignedTinyInteger('mes_primera_pension')->default(3);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['colegio_id', 'anio_escolar_id', 'grado_id'], 'tarifas_colegio_anio_grado_unique');
            $table->unique(['colegio_id', 'id'], 'tarifas_colegio_id_id_unique');
            $table->foreign(['colegio_id', 'anio_escolar_id'], 'tarifas_colegio_anio_foreign')
                ->references(['colegio_id', 'id'])
                ->on('anios_escolares');
            $table->foreign(['colegio_id', 'grado_id'], 'tarifas_colegio_grado_foreign')
                ->references(['colegio_id', 'id'])
                ->on('grados');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tarifas');
    }
};
