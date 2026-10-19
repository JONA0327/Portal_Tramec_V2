<?php

namespace App\Services;

use App\Events\ExistenciaChanged;
use App\Support\StockHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cliente del microservicio de existencias para CANTIDADES (no catálogo, ver
 * StockService para eso). Misma arquitectura: caché local + sync completo
 * programado + webhook en tiempo real (servicio-stock::_vigilar_existencias,
 * hilo aparte del que vigila el catálogo).
 *
 * /api/stocks/{empresa} es el único endpoint bulk fiable: /api/stocks/{id_producto}
 * (un solo segmento) está tapado por /api/stocks/{empresa} del lado de
 * servicio-stock (mismo shape de ruta, la de empresa se registró primero en
 * FastAPI) — probado en vivo, así que acá nunca se usa esa forma.
 */
class StockLevelService
{
    private const CACHE_KEY_LEVELS = 'stock_service.levels';
    private const CACHE_KEY_LAST_SYNC = 'stock_service.levels.last_full_sync';

    private const EMPRESAS = ['tramec', 'omec'];

    // Mismo criterio que StockService::MAX_INDIVIDUAL_BROADCASTS: si cambió
    // "todo" (primer sync, caché borrada) no se manda un evento de Pusher por
    // cada fila, una por una y de forma sincrónica.
    private const MAX_INDIVIDUAL_BROADCASTS = 50;

    public function getLevels(): array
    {
        return Cache::get(self::CACHE_KEY_LEVELS, []);
    }

    public function lastSyncAt(): ?string
    {
        return Cache::get(self::CACHE_KEY_LAST_SYNC);
    }

    /**
     * Trae las existencias completas de TRAMEC + OMEC, las compara contra la
     * caché anterior y dispara ExistenciaChanged solo por las que de verdad
     * cambiaron. Devuelve el resultado.
     */
    public function sync(): array
    {
        $before = [];
        foreach ($this->getLevels() as $level) {
            $before[$this->levelId($level)] = $level;
        }

        try {
            $after = $this->fetchAllLevels();
        } catch (\Illuminate\Http\Client\ConnectionException|\Illuminate\Http\Client\RequestException $e) {
            StockHealth::down();
            throw $e; // la caché anterior queda intacta
        }
        StockHealth::up();

        $changed = [];
        foreach ($after as $id => $level) {
            if (($before[$id] ?? null) !== $level) {
                $changed[] = $level;
            }
        }

        if ($before !== [] && count($changed) <= self::MAX_INDIVIDUAL_BROADCASTS) {
            foreach ($changed as $level) {
                event(new ExistenciaChanged($level));
            }
        }

        $levels = array_values($after);

        Cache::forever(self::CACHE_KEY_LEVELS, $levels);
        Cache::forever(self::CACHE_KEY_LAST_SYNC, now()->toDateTimeString());

        return $levels;
    }

    /**
     * Actualiza una sola fila (producto+almacén) en la caché, llamada desde el
     * webhook de servicio-stock (ver StockWebhookController). Dispara
     * ExistenciaChanged solo si la cantidad realmente cambió.
     */
    public function applyChange(array $level): void
    {
        $id = $this->levelId($level);

        if ($id === null) {
            return;
        }

        $levels = [];
        foreach ($this->getLevels() as $existing) {
            $levels[$this->levelId($existing)] = $existing;
        }

        if (($levels[$id] ?? null) === $level) {
            return;
        }

        $levels[$id] = $level;

        Cache::forever(self::CACHE_KEY_LEVELS, array_values($levels));
        Cache::forever(self::CACHE_KEY_LAST_SYNC, now()->toDateTimeString());

        event(new ExistenciaChanged($level));
    }

    /**
     * @return array<string, array> existencias indexadas por "empresa:id_producto:id_almacen"
     */
    private function fetchAllLevels(): array
    {
        $levels = [];

        foreach (self::EMPRESAS as $empresa) {
            $rows = $this->client()->get("/api/stocks/{$empresa}")->throw()->json();

            foreach ($rows as $row) {
                $levels[$this->levelId($row)] = $row;
            }
        }

        return $levels;
    }

    private function levelId(array $item): ?string
    {
        $idProducto = $item['id_producto'] ?? null;
        $idAlmacen = $item['id_almacen'] ?? null;

        if ($idProducto === null || $idAlmacen === null) {
            return null;
        }

        $empresa = $item['empresa_codigo'] ?? 'TRAMEC';

        return "{$empresa}:{$idProducto}:{$idAlmacen}";
    }

    private function client()
    {
        return Http::withToken(config('services.stock.key'))
            ->baseUrl(config('services.stock.url'))
            ->timeout(20);
    }
}
