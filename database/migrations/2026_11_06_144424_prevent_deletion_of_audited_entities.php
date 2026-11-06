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
        Schema::table('auditorias', static function (Blueprint $table): void {
            $table->dropForeign(['colegio_id']);
            $table->dropForeign(['usuario_id']);
            $table->foreign('colegio_id')->references('id')->on('colegios')->restrictOnDelete();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auditorias', static function (Blueprint $table): void {
            $table->dropForeign(['colegio_id']);
            $table->dropForeign(['usuario_id']);
            $table->foreign('colegio_id')->references('id')->on('colegios')->nullOnDelete();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->nullOnDelete();
        });
    }
};
