<?php

namespace App\Console\Commands;

use App\Services\ProductErpMatcher;
use Illuminate\Console\Command;

class MatchProductsErp extends Command
{
    protected $signature = 'products:match-erp';

    protected $description = 'Empareja los productos locales (precios por canal) con el catálogo del ERP por código';

    public function handle(ProductErpMatcher $matcher): int
    {
        $stats = $matcher->match();

        $this->info(sprintf(
            'Total: %d | ya emparejados: %d | emparejados ahora: %d | sin emparejar: %d',
            $stats['total'],
            $stats['matched_before'],
            $stats['matched_now'],
            $stats['unmatched'],
        ));

        return self::SUCCESS;
    }
}
