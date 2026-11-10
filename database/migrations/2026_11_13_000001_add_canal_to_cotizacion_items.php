<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Canal (columna de precio) propio de cada renglón; vacío = el general de la cotización.
    public function up(): void
    {
        if (! Schema::hasColumn('cotizacion_items', 'canal')) {
            Schema::table('cotizacion_items', function (Blueprint $table) {
                $table->string('canal', 30)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cotizacion_items', 'canal')) {
            Schema::table('cotizacion_items', function (Blueprint $table) {
                $table->dropColumn('canal');
            });
        }
    }
};
