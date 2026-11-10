<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Preferencia del administrador: "Ver ocultos" en Existencias se conserva (como "Agrupar por modelo").
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'ver_ocultos_existencias')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('ver_ocultos_existencias')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'ver_ocultos_existencias')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('ver_ocultos_existencias');
            });
        }
    }
};
