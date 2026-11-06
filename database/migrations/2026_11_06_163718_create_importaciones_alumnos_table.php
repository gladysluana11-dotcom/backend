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
        Schema::create('importaciones_alumnos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->char('token_hash', 64);
            $table->string('ruta_archivo');
            $table->char('sha256', 64);
            $table->dateTime('expira_en');
            $table->dateTime('confirmada_en')->nullable();
            $table->timestamps();

            $table->unique(['colegio_id', 'usuario_id', 'token_hash'], 'importaciones_alumnos_token_unique');
            $table->index(['colegio_id', 'usuario_id', 'expira_en']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('importaciones_alumnos');
    }
};
