<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Igual que con products/idERP: la empresa no vivía en ningún lado (todo era
     * TRAMEC históricamente). Ahora que existe /api/almacenes/unificado (id_erp ya
     * corregido/único, verificado en vivo contra el ERP), se agregan las columnas y
     * se hace el backfill de los 7 almacenes activos que sí matchean.
     *
     * Importante: id_erp (para EMPAREJAR) no es lo mismo que el id real del almacén
     * dentro de su empresa (para CONSULTAR /api/stocks). Para TRAMEC coinciden, pero
     * para OMEC FABRICA no: id_erp=8, pero el id real en la BD de OMEC es 3.
     *
     * "MDG" (idERP=998) no tiene equivalente en el ERP y ya no se usa (confirmado
     * con el cliente) — queda sin idERP_empresa, se va a mostrar como sin emparejar.
     */
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('idERP_empresa', 10)->nullable()->after('idERP');
            $table->unsignedInteger('almacen_erp_id')->nullable()->after('idERP_empresa');
        });

        // idERP (local) => [empresa, id real del almacén en esa empresa]
        $mapa = [
            0 => ['tramec', 0], // DELTA Schenker
            1 => ['tramec', 1], // QRO
            3 => ['tramec', 3], // SLP
            4 => ['tramec', 4], // MTY
            5 => ['tramec', 5], // GDL FI
            6 => ['tramec', 6], // GDL JG
            8 => ['omec', 3],   // OMEC FABRICA (id_erp=8, pero el id real en OMEC es 3)
        ];

        foreach ($mapa as $idErp => [$empresa, $idReal]) {
            DB::table('warehouses')->where('idERP', $idErp)->update([
                'idERP_empresa' => $empresa,
                'almacen_erp_id' => $idReal,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['idERP_empresa', 'almacen_erp_id']);
        });
    }
};
