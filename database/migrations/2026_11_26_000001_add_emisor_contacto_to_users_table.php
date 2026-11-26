<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Correo y teléfono que cada usuario pone como emisor en sus cotizaciones (null = nunca los cambió: se sugiere el de acceso).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'cotizacion_email')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('cotizacion_email', 191)->nullable();
                $table->string('cotizacion_telefono', 60)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['cotizacion_email', 'cotizacion_telefono']));
    }
};
