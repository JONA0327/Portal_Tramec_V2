<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Cliente de los endpoints de blacklist de servicio-stock (GET/POST/DELETE
 * /api/blacklist/{empresa}). La API solo soporta reemplazar la lista completa
 * o vaciarla — add()/remove() la leen primero y postean la lista modificada.
 */
class BlacklistService
{
    public function list(string $empresa): array
    {
        $response = $this->client()->get("/api/blacklist/{$empresa}")->throw()->json();

        return $response['product_ids'] ?? [];
    }

    public function add(string $empresa, int $productId): void
    {
        $ids = $this->list($empresa);

        if (in_array($productId, $ids, true)) {
            return;
        }

        $ids[] = $productId;
        $this->replace($empresa, $ids);
    }

    public function remove(string $empresa, int $productId): void
    {
        $ids = array_values(array_diff($this->list($empresa), [$productId]));
        $this->replace($empresa, $ids);
    }

    private function replace(string $empresa, array $productIds): void
    {
        $this->client()->post("/api/blacklist/{$empresa}", ['product_ids' => $productIds])->throw();
    }

    private function client()
    {
        return Http::withToken(config('services.stock.key'))
            ->baseUrl(config('services.stock.url'))
            ->timeout(20);
    }
}
