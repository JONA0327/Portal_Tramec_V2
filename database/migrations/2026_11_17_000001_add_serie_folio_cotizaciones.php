<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Serie y folio de la cotización: cada usuario conserva la última serie que usó (users.cotizacion_serie); el folio es un
// número (el portal permite repetidos). `folio` (texto) sigue existiendo y guarda serie + número ya armado.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'cotizacion_serie')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('cotizacion_serie', 20)->nullable();
            });
        }

        if (! Schema::hasColumn('cotizaciones', 'serie')) {
            Schema::table('cotizaciones', function (Blueprint $table) {
                $table->string('serie', 20)->nullable();
                $table->unsignedInteger('folio_numero')->nullable();
            });
        }

        if (! Schema::hasColumn('cotizaciones_historial', 'serie')) {
            Schema::table('cotizaciones_historial', function (Blueprint $table) {
                $table->string('serie', 20)->nullable()->index();
                $table->unsignedInteger('folio_numero')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('cotizacion_serie'));
        Schema::table('cotizaciones', fn (Blueprint $t) => $t->dropColumn(['serie', 'folio_numero']));
        Schema::table('cotizaciones_historial', fn (Blueprint $t) => $t->dropColumn(['serie', 'folio_numero']));
    }
};
