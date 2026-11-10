<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Preferencia del usuario: "Agrupar por modelo" en Existencias se conserva entre sesiones.
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'agrupar_existencias')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('agrupar_existencias')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'agrupar_existencias')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('agrupar_existencias');
            });
        }
    }
};
