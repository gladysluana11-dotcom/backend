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
        Schema::create('secuencia_constancias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->string('serie', 20);
            $table->unsignedBigInteger('ultimo_numero')->default(0);
            $table->timestamps();

            $table->unique(['colegio_id', 'serie'], 'secuencia_constancias_colegio_serie_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('secuencia_constancias');
    }
};
