<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add the closed state and composite tenant key. */
    public function up(): void
    {
        Schema::table('anios_escolares', function (Blueprint $table): void {
            $table->boolean('cerrado')->default(false)->after('activo');
            $table->unique(['colegio_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('anios_escolares', function (Blueprint $table): void {
            $table->dropUnique(['colegio_id', 'id']);
            $table->dropColumn('cerrado');
        });
    }
};
