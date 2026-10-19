<?php

namespace App\Listeners;

use App\Events\ProductChanged;
use App\Models\Product;

/**
 * El ERP manda sobre nombre/código. Cuando StockService detecta un cambio real
 * en un producto (sync completo o webhook de servicio-stock), este listener
 * actualiza el nombre/código del registro local de precios vinculado a ese
 * idERP — así la tabla de precios nunca queda con datos viejos.
 *
 * Si ningún registro local está vinculado a ese idERP todavía, no hace nada:
 * ese caso se muestra como "Nuevo" en /productos hasta que alguien le cargue
 * un precio (ver ProductsController@store).
 */
class SyncLocalProductFromErp
{
    public function handle(ProductChanged $event): void
    {
        $id = $event->product['id'] ?? null;
        $codigo = $event->product['codigo'] ?? null;

        if ($id === null || $codigo === null) {
            return;
        }

        $empresa = $event->product['empresa_codigo'] ?? 'TRAMEC';

        Product::where('idERP', $id)
            ->where('idERP_empresa', $empresa)
            ->update([
                'name' => $codigo,
                // name2 es el mismo código sin caracteres especiales (así lo traía
                // v1: "ASD-A2-2023-F" -> "ASDA22023F"), se recalcula junto con name.
                'name2' => preg_replace('/[^A-Za-z0-9]/', '', $codigo),
            ]);
    }
}
