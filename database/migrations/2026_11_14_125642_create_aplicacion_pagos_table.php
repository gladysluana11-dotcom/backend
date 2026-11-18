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
        Schema::create('aplicacion_pagos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('pago_id');
            $table->unsignedBigInteger('cuota_id');
            $table->decimal('monto_aplicado', 10, 2);
            $table->timestamps();

            $table->unique(['colegio_id', 'pago_id', 'cuota_id'], 'aplicacion_pagos_colegio_pago_cuota_unique');
            $table->index(['colegio_id', 'cuota_id'], 'aplicacion_pagos_colegio_cuota_index');
            $table->foreign(['colegio_id', 'pago_id'], 'aplicacion_pagos_colegio_pago_foreign')
                ->references(['colegio_id', 'id'])->on('pagos');
            $table->foreign(['colegio_id', 'cuota_id'], 'aplicacion_pagos_colegio_cuota_foreign')
                ->references(['colegio_id', 'id'])->on('cuotas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aplicacion_pagos');
    }
};
