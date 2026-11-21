<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Filtro por marca de la regla de precios (products.brand): vacío = todas las marcas.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('price_rules', 'marcas')) {
            Schema::table('price_rules', fn (Blueprint $t) => $t->json('marcas')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('price_rules', fn (Blueprint $t) => $t->dropColumn('marcas'));
    }
};
