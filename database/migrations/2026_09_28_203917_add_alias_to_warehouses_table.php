<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `name` lo sigue manejando WarehouseErpMatcher (auto-sincronizado desde el
     * nombre real de CONTPAQi, nunca a mano). `alias` es lo opuesto: un nombre
     * elegido a propósito para mostrar en el portal cuando el de CONTPAQi es
     * poco claro (ej. "Almacen Uno" de OMEC se ve como "QRO" en Existencias),
     * que el matcher nunca toca. Si `alias` es null, se usa `name` tal cual.
     */
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('alias')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('alias');
        });
    }
};
