<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Cambio de criterio: en vez de listar los productos que quedan fuera, se listan
    // los elegidos. Vacío = la regla aplica a todos los que coincidan con el filtro.
    public function up(): void
    {
        Schema::table('price_rules', function (Blueprint $table) {
            $table->dropColumn('excluidos');
            $table->json('incluidos')->nullable()->after('campo');
        });
    }

    public function down(): void
    {
        Schema::table('price_rules', function (Blueprint $table) {
            $table->dropColumn('incluidos');
            $table->json('excluidos')->nullable()->after('campo');
        });
    }
};
