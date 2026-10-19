<?php

namespace App\Console\Commands;

use App\Services\StockLevelService;
use Illuminate\Console\Command;

class SyncStockLevels extends Command
{
    protected $signature = 'stock-levels:sync';

    protected $description = 'Sincroniza las existencias (cantidad por almacén) del microservicio hacia la caché local';

    public function handle(StockLevelService $stockLevels): int
    {
        try {
            $levels = $stockLevels->sync();
        } catch (\Illuminate\Http\Client\ConnectionException|\Illuminate\Http\Client\RequestException $e) {
            $this->error('servicio-stock no responde, se conserva la caché: '.$e->getMessage());

            return self::FAILURE;
        }


        $this->info(sprintf('Existencias en caché: %d filas (último sync completo: %s).', count($levels), $stockLevels->lastSyncAt() ?? 'nunca'));

        return self::SUCCESS;
    }
}
