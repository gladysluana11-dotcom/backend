<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create per-school settings. */
    public function up(): void
    {
        Schema::create('configuraciones_colegio', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero_cuotas_pension')->default(10);
            $table->unsignedTinyInteger('dia_vencimiento')->default(5);
            $table->string('serie_recibos', 20)->default('R001');
            $table->string('frecuencia_registro_avance', 20)->default('semanal');
            $table->boolean('recargo_mora_activo')->default(false);
            $table->timestamps();

            $table->unique('colegio_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracion_colegios');
    }
};
