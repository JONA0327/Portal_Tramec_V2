<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Marca si servicio-stock está caído. Lo prenden los syncs y las llamadas en
 * vivo que fallan, y lo apaga el próximo sync exitoso. El portal sigue
 * funcionando con la caché/BD y solo avisa en pantalla (x-stock-status).
 */
class StockHealth
{
    private const KEY = 'stock_service.down_since';

    public static function down(): void
    {
        Cache::add(self::KEY, now()->toDateTimeString()); // add = conserva la primera hora de caída
    }

    public static function up(): void
    {
        Cache::forget(self::KEY);
    }

    /**
     * Ping barato (3 s) al servicio; el resultado se reusa 10 s para que ni
     * las páginas ni el sondeo del navegador lo golpeen en cada request.
     */
    public static function check(): void
    {
        if (! Cache::add('stock_service.last_ping', true, 10)) {
            return;
        }

        try {
            Http::withToken(config('services.stock.key'))
                ->baseUrl(config('services.stock.url'))
                ->timeout(3)
                ->get('/api/almacenes/tramec')
                ->throw();
            self::up();
        } catch (ConnectionException|\Illuminate\Http\Client\RequestException) {
            self::down();
        }
    }

    public static function downSince(): ?string
    {
        return Cache::get(self::KEY);
    }
}
