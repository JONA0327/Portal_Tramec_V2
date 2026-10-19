<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Códigos de producto (en mayúsculas) que, aun coincidiendo con el filtro de la regla, quedan fuera.
    public function up(): void
    {
        Schema::table('price_rules', function (Blueprint $table) {
            $table->json('excluidos')->nullable()->after('campo');
        });
    }

    public function down(): void
    {
        Schema::table('price_rules', function (Blueprint $table) {
            $table->dropColumn('excluidos');
        });
    }
};
