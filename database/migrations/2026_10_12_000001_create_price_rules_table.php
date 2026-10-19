<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reglas de descuento/aumento que se aplican SOLO al mostrar precios (ver
     * App\Services\PriceAdjuster). Nunca modifican la tabla `products`.
     * buscar: términos separados por coma (vacío = todos los productos).
     * columnas / niveles: JSON; niveles = users.level que ven la regla (null = todos).
     */
    public function up(): void
    {
        Schema::create('price_rules', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tipo', 10); // descuento | aumento
            $table->decimal('porcentaje', 5, 2);
            $table->string('buscar')->nullable();
            $table->string('campo', 10)->default('ambos'); // codigo | nombre | ambos
            $table->json('columnas');
            $table->json('niveles')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_rules');
    }
};
