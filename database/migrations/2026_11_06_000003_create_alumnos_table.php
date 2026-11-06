<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('documento_identidad')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('sexo')->nullable();
            $table->string('direccion')->nullable();
            $table->string('estado')->default('activo');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['colegio_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumnos');
    }
};
