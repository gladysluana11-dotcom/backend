<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create academic import records. */
    public function up(): void
    {
        Schema::create('importaciones_academicas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->unsignedBigInteger('anio_escolar_id');
            $table->char('token_hash', 64)->unique();
            $table->string('ruta_archivo');
            $table->char('sha256', 64);
            $table->timestamp('expira_en');
            $table->timestamp('confirmada_en')->nullable();
            $table->timestamps();

            $table->foreign(['colegio_id', 'anio_escolar_id'])
                ->references(['colegio_id', 'id'])
                ->on('anios_escolares')
                ->restrictOnDelete();
            $table->index(['colegio_id', 'expira_en']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('importaciones_academicas');
    }
};
