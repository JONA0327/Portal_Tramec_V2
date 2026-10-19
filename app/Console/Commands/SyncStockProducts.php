<?php

namespace App\Console\Commands;

use App\Services\StockService;
use Illuminate\Console\Command;

class SyncStockProducts extends Command
{
    protected $signature = 'stock:sync';

    protected $description = 'Sincroniza el catálogo de productos del microservicio de existencias hacia la caché local';

    public function handle(StockService $stock): int
    {
        try {
            $products = $stock->sync();
        } catch (\Illuminate\Http\Client\ConnectionException|\Illuminate\Http\Client\RequestException $e) {
            $this->error('servicio-stock no responde, se conserva la caché: '.$e->getMessage());

            return self::FAILURE;
        }


        $this->info(sprintf('Catálogo en caché: %d productos (último sync completo: %s).', count($products), $stock->lastSyncAt() ?? 'nunca'));

        return self::SUCCESS;
    }
}
