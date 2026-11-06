<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create per-school feature flags. */
    public function up(): void
    {
        Schema::create('funciones_colegio', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('colegio_id')->constrained('colegios')->cascadeOnDelete();
            $table->boolean('portal_apoderados')->default(false);
            $table->boolean('notificaciones_push')->default(false);
            $table->boolean('recargo_mora')->default(false);
            $table->timestamps();

            $table->unique('colegio_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funciones_colegio');
    }
};
