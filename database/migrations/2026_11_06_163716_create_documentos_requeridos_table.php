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
        Schema::create('documentos_requeridos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->string('nombre');
            $table->boolean('obligatorio')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['colegio_id', 'nombre']);
            $table->unique(['colegio_id', 'id'], 'documentos_requeridos_colegio_id_id_unique');
        });

        Schema::create('matricula_documentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('matricula_id');
            $table->unsignedBigInteger('documento_requerido_id');
            $table->string('estado')->default('pendiente');
            $table->date('fecha_entrega')->nullable();
            $table->timestamps();

            $table->unique(
                ['colegio_id', 'matricula_id', 'documento_requerido_id'],
                'matricula_documentos_unico',
            );
            $table->foreign(['colegio_id', 'matricula_id'])
                ->references(['colegio_id', 'id'])
                ->on('matriculas')
                ->cascadeOnDelete();
            $table->foreign(['colegio_id', 'documento_requerido_id'])
                ->references(['colegio_id', 'id'])
                ->on('documentos_requeridos')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matricula_documentos');
        Schema::dropIfExists('documentos_requeridos');
    }
};
