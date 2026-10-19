<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Antes de esta migración, "idERP" era el único campo y no distinguía empresa.
     * Para OMEC se usaba un truco de texto: idERP = "777" + el id real de OMEC
     * (ej. "7777" -> producto OMEC #7), detectable porque brand='OMEC' en esas
     * filas (validado: las 96 filas brand=OMEC son exactamente las 96 filas con
     * idERP tipo "777xxx", sin excepciones). Esta migración agrega la columna de
     * empresa y hace ese backfill una sola vez, abandonando el truco del "777":
     * de acá en adelante idERP guarda el id real, siempre acompañado de su empresa.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('idERP_empresa', 10)->nullable()->after('idERP');
        });

        DB::table('products')
            ->where('brand', 'OMEC')
            ->where('idERP', 'like', '777%')
            ->orderBy('id')
            ->each(function (object $product) {
                DB::table('products')->where('id', $product->id)->update([
                    'idERP' => substr($product->idERP, 3),
                    'idERP_empresa' => 'OMEC',
                ]);
            });

        DB::table('products')
            ->whereNotNull('idERP')
            ->where('idERP', '!=', '')
            ->whereNull('idERP_empresa')
            ->update(['idERP_empresa' => 'TRAMEC']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No se revierte el backfill del "777": una vez limpiado, no hay forma de
        // saber cuáles idERP de OMEC llevaban ese prefijo originalmente.
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('idERP_empresa');
        });
    }
};
