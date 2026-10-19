<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Existencias cargadas desde Excel que no están en el catálogo del ERP:
     * viven solo acá (no en `products`), con id_producto sintético >= 1e9.
     */
    public function up(): void
    {
        Schema::table('existencia_overrides', function (Blueprint $table) {
            $table->string('codigo', 100)->nullable()->after('id_almacen');
            $table->string('nombre', 191)->nullable()->after('codigo');
        });
    }

    public function down(): void
    {
        Schema::table('existencia_overrides', function (Blueprint $table) {
            $table->dropColumn(['codigo', 'nombre']);
        });
    }
};
