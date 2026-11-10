<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Historial de cotizaciones guardadas al descargar el PDF. `datos` es una foto completa (datos generales y renglones)
// para poder volver a descargar el mismo PDF aunque cambien los precios o el catálogo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones_historial', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('folio', 80)->nullable();
            $table->string('moneda', 5);
            $table->date('fecha')->nullable();
            $table->string('cliente_empresa', 191)->nullable();
            $table->string('emisor', 191)->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->json('datos');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizaciones_historial');
    }
};
