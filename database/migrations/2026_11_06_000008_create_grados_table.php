<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grados', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('nivel_id');
            $table->string('nombre');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['colegio_id', 'nivel_id', 'nombre']);
            $table->unique(['colegio_id', 'id']);
            $table->foreign(['colegio_id', 'nivel_id'])
                ->references(['colegio_id', 'id'])
                ->on('niveles')
                ->cascadeOnDelete();
            $table->index(['colegio_id', 'nivel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grados');
    }
};
