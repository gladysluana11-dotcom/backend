<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apoderados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('alumno_id');
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('parentesco');
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->boolean('responsable_pago')->default(false);
            $table->timestamps();

            $table->foreign(['colegio_id', 'alumno_id'])
                ->references(['colegio_id', 'id'])
                ->on('alumnos')
                ->cascadeOnDelete();
            $table->index(['colegio_id', 'alumno_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apoderados');
    }
};
