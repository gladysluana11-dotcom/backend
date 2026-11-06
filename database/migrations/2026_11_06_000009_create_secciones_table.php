<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('grado_id');
            $table->string('nombre');
            $table->unsignedSmallInteger('capacidad')->default(30);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['colegio_id', 'grado_id', 'nombre']);
            $table->foreign(['colegio_id', 'grado_id'])
                ->references(['colegio_id', 'id'])
                ->on('grados')
                ->cascadeOnDelete();
            $table->index(['colegio_id', 'grado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secciones');
    }
};
