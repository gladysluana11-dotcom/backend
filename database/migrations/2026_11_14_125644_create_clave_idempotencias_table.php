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
        Schema::create('clave_idempotencias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->unsignedBigInteger('usuario_id');
            $table->string('clave', 255);
            $table->char('hash_cuerpo', 64);
            $table->json('respuesta');
            $table->timestamp('vence_en');
            $table->timestamps();

            $table->unique(
                ['colegio_id', 'usuario_id', 'clave'],
                'clave_idempotencias_colegio_usuario_clave_unique',
            );
            $table->index(['colegio_id', 'vence_en'], 'clave_idempotencias_colegio_vence_index');
            $table->foreign(['colegio_id', 'usuario_id'], 'clave_idempotencias_colegio_usuario_foreign')
                ->references(['colegio_id', 'id'])->on('usuarios');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clave_idempotencias');
    }
};
