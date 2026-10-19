<?php

namespace App\Http\Controllers;

use App\Services\StockLevelService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Recibe el aviso de servicio-stock apenas detecta un cambio real, sin esperar
 * al sync programado cada 15 min. Un mismo endpoint para los dos watchers que
 * corren allá (main.py::_vigilar_catalogo y ::_vigilar_existencias) — se
 * distinguen por el campo "tipo" del payload ("existencia" o, por defecto/
 * compatibilidad con el watcher de catálogo que no lo manda, "producto").
 */
class StockWebhookController extends Controller
{
    public function handle(Request $request, StockService $stock, StockLevelService $stockLevels): Response
    {
        $secret = config('services.stock.webhook_secret');

        if (! $secret || ! hash_equals($secret, (string) $request->header('X-Webhook-Secret'))) {
            abort(401, 'Webhook secret inválido o no configurado');
        }

        $payload = $request->json()->all();
        $tipo = $payload['tipo'] ?? 'producto';
        unset($payload['tipo']);

        if ($tipo === 'existencia') {
            if (! isset($payload['id_producto']) || ! isset($payload['id_almacen'])) {
                abort(422, 'Falta "id_producto" o "id_almacen" en la existencia');
            }

            // Sin el campo "tipo" (ya lo sacamos arriba): así el shape queda
            // igual al que arma StockLevelService::fetchAllLevels() en el sync
            // completo, no algo distinto según si llegó por webhook o por sync.
            $stockLevels->applyChange($payload);

            return response()->noContent();
        }

        if (! isset($payload['id'])) {
            abort(422, 'Falta "id" en el producto');
        }

        $stock->applyChange($payload);

        return response()->noContent();
    }
}
