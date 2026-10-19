<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ids de users a los que aplica la regla (null = todos los usuarios que pasen el filtro de niveles).
    public function up(): void
    {
        Schema::table('price_rules', function (Blueprint $table) {
            $table->json('usuarios')->nullable()->after('niveles');
        });
    }

    public function down(): void
    {
        Schema::table('price_rules', function (Blueprint $table) {
            $table->dropColumn('usuarios');
        });
    }
};
