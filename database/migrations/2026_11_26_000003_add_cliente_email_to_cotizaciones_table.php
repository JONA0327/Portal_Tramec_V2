<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Correo del cliente (destinatario) de la cotización. El del emisor ya no se captura ni se imprime.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cotizaciones', 'cliente_email')) {
            Schema::table('cotizaciones', fn (Blueprint $t) => $t->string('cliente_email', 191)->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('cotizaciones', fn (Blueprint $t) => $t->dropColumn('cliente_email'));
    }
};
