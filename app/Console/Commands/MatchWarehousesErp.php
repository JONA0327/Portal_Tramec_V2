<?php

namespace App\Console\Commands;

use App\Services\WarehouseErpMatcher;
use Illuminate\Console\Command;

class MatchWarehousesErp extends Command
{
    protected $signature = 'warehouses:match-erp';

    protected $description = 'Empareja los almacenes locales con el catálogo unificado de almacenes del ERP';

    public function handle(WarehouseErpMatcher $matcher): int
    {
        $stats = $matcher->match();

        $this->info(sprintf(
            'Total: %d | ya emparejados: %d | nombres actualizados: %d | emparejados ahora: %d | sin emparejar: %d | nuevos en el ERP: %d',
            $stats['total'],
            $stats['matched_before'],
            $stats['renamed'],
            $stats['matched_now'],
            $stats['unmatched'],
            count($stats['nuevos']),
        ));

        return self::SUCCESS;
    }
}
