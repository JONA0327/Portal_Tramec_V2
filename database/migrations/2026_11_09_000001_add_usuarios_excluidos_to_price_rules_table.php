<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Usuarios a los que la regla NO se aplica (excepciones): p. ej. quienes sí ven el precio real de la
    // serie C2000 Plus mientras para todos los demás está en $0.00 (la casilla "Ver precios C2000 Plus" de v1).
    public function up(): void
    {
        Schema::table('price_rules', function (Blueprint $table) {
            $table->json('usuarios_excluidos')->nullable()->after('usuarios');
        });
    }

    public function down(): void
    {
        Schema::table('price_rules', function (Blueprint $table) {
            $table->dropColumn('usuarios_excluidos');
        });
    }
};
