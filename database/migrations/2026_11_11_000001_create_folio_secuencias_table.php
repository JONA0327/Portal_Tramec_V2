<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Contador de folios consecutivos (uno por serie). Aparte de `cotizaciones` para que vaciar el carrito no reinicie la serie.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folio_secuencias', function (Blueprint $table) {
            $table->string('serie', 20)->primary();
            $table->unsignedInteger('ultimo')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folio_secuencias');
    }
};
