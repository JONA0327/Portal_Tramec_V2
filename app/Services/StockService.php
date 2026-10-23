<?php

namespace App\Services;

use App\Events\ProductChanged;
use App\Models\ExistenciaOverride;
use App\Support\StockHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cliente del microservicio de existencias (servicio-stock). Mantiene una copia del
 * catálogo de productos en caché para que las páginas del portal no tengan que llamar
 * a la API externa en cada request.
 *
 * Multi-empresa: servicio-stock expone dos bases (TRAMEC y OMEC), cada una con su
 * propio CIDPRODUCTO — el mismo id existe en ambas y significa productos distintos.
 * Por eso la caché no se indexa por "id" solo, sino por "empresa_codigo:id".
 *
 * Por qué no usa /api/cambios: esa ruta solo rastrea MOVIMIENTOS DE STOCK (tabla
 * admMovimientos: entradas/salidas), no ediciones del catálogo (nombre/código en
 * admProductos). Probado en vivo: cambiar el nombre de un producto directo en la BD
 * nunca aparece ahí. Como lo que de verdad importa acá es detectar cualquier cambio
 * del catálogo, cada sync trae el catálogo completo de nuevo (de las dos empresas)
 * y lo compara contra la caché.
 */
class StockService
{
    private const CACHE_KEY_PRODUCTS = 'stock_service.products';
    private const CACHE_KEY_LAST_SYNC = 'stock_service.last_full_sync';

    // Al probar la API en vivo, limit=1000 respondió bien pero limit=5000 devolvió un
    // solo registro corrupto (bug del lado del servicio) — 500 se queda con margen de sobra.
    private const FETCH_PAGE_SIZE = 500;

    // Las empresas que expone servicio-stock (ver /api/productos/{empresa}).
    private const EMPRESAS = ['tramec', 'omec'];

    // Si un sync detecta más cambios que esto de una, algo raro pasó (primer sync,
    // caché borrada, cambio de formato) y no se manda un evento de Pusher por cada
    // uno — ver el comentario en sync().
    private const MAX_INDIVIDUAL_BROADCASTS = 50;

    /**
     * Productos en caché, listos para mostrar. No llama a la API: si nunca se sincronizó,
     * devuelve un arreglo vacío (llamar a sync() primero, típicamente desde el scheduler).
     */
    public function getProducts(): array
    {
        return Cache::get(self::CACHE_KEY_PRODUCTS, []);
    }

    /**
     * Última vez que la caché cambió de verdad: sea por un sync completo o por un
     * único producto llegado por webhook (ver applyChange()). Es lo que muestra
     * "Catálogo actualizado" en la vista.
     */
    public function lastSyncAt(): ?string
    {
        return Cache::get(self::CACHE_KEY_LAST_SYNC);
    }

    /**
     * Trae el catálogo completo de TRAMEC + OMEC, lo compara contra la caché anterior
     * y dispara ProductChanged solo por los productos que de verdad cambiaron. Devuelve
     * el catálogo resultante.
     */
    public function sync(): array
    {
        $before = [];
        foreach ($this->getProducts() as $product) {
            $before[$this->productId($product)] = $product;
        }

        try {
            $after = $this->fetchAllProducts();
        } catch (\Illuminate\Http\Client\ConnectionException|\Illuminate\Http\Client\RequestException $e) {
            StockHealth::down();
            throw $e; // la caché anterior queda intacta
        }
        StockHealth::up();

        $changed = [];
        foreach ($after as $id => $product) {
            if (($before[$id] ?? null) !== $product) {
                $changed[] = $product;
            }
        }

        // Si prácticamente todo "cambió" (caché vacía, primer sync, o se cambió el
        // formato de la clave como al agregar multi-empresa) no tiene sentido — ni es
        // seguro — mandar un evento a Pusher por cada uno de miles de productos, uno
        // por uno y de forma sincrónica. Eso se probó: tarda minutos y puede comerse
        // la cuota diaria gratis de Pusher. Un resync masivo no es información que
        // alguien mirando la tabla necesite ver parpadear producto por producto.
        if ($before !== [] && count($changed) <= self::MAX_INDIVIDUAL_BROADCASTS) {
            foreach ($changed as $product) {
                event(new ProductChanged($product));
            }
        }

        $products = array_values($after);

        Cache::forever(self::CACHE_KEY_PRODUCTS, $products);
        Cache::forever(self::CACHE_KEY_LAST_SYNC, now()->toDateTimeString());

        // Lo que solo existía por el Excel de Schenker y ya se dio de alta en CONTPAQi se liga al producto real.
        ExistenciaOverride::ligarConCatalogo($products);

        return $products;
    }

    /**
     * Actualiza un solo producto en la caché (llamado desde el webhook de
     * servicio-stock, ver StockWebhookController). El payload ya trae
     * empresa_codigo/empresa_nombre/empresa_rfc (ver main.py::_avisar_al_portal).
     * Dispara ProductChanged solo si el dato realmente es distinto al que ya
     * estaba cacheado.
     */
    public function applyChange(array $product): void
    {
        $id = $this->productId($product);

        if ($id === null) {
            return;
        }

        $products = [];
        foreach ($this->getProducts() as $existing) {
            $products[$this->productId($existing)] = $existing;
        }

        if (($products[$id] ?? null) === $product) {
            return;
        }

        $products[$id] = $product;

        Cache::forever(self::CACHE_KEY_PRODUCTS, array_values($products));
        Cache::forever(self::CACHE_KEY_LAST_SYNC, now()->toDateTimeString());

        ExistenciaOverride::ligarConCatalogo([$product]);

        event(new ProductChanged($product));
    }

    /**
     * @return array<string, array> productos indexados por "empresa_codigo:id"
     */
    private function fetchAllProducts(): array
    {
        $products = [];

        foreach (self::EMPRESAS as $empresa) {
            $offset = 0;

            do {
                $page = $this->client()
                    ->get("/api/productos/{$empresa}", ['limit' => self::FETCH_PAGE_SIZE, 'offset' => $offset])
                    ->throw()
                    ->json();

                foreach ($page as $item) {
                    $products[$this->productId($item)] = $item;
                }

                $offset += self::FETCH_PAGE_SIZE;
            } while (count($page) === self::FETCH_PAGE_SIZE);
        }

        return $products;
    }

    /**
     * Clave compuesta "empresa_codigo:id": TRAMEC y OMEC tienen cada una su propio
     * CIDPRODUCTO, así que el mismo id en las dos bases son productos distintos.
     * Si por algo llega sin empresa_codigo (no debería), se asume TRAMEC.
     */
    private function productId(array $item): ?string
    {
        $id = $item['id'] ?? $item['id_producto'] ?? $item['ID'] ?? null;

        if ($id === null) {
            return null;
        }

        $empresa = $item['empresa_codigo'] ?? 'TRAMEC';

        return "{$empresa}:{$id}";
    }

    private function client()
    {
        return Http::withToken(config('services.stock.key'))
            ->baseUrl(config('services.stock.url'))
            ->timeout(20);
    }
}
