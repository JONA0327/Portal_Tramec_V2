<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Empareja los productos locales (tabla `products`, precios por canal) con el
 * catálogo real del ERP (servicio-stock, ver StockService). El campo idERP ya
 * no se edita a mano: lo pone este matcher, comparando por código exacto.
 *
 * Reglas:
 *  - Si un producto ya tiene idERP+idERP_empresa y ese id TODAVÍA existe en el
 *    catálogo actual de esa empresa, no se toca (no pisa un vínculo bueno).
 *  - Si no tiene, o el que tenía ya no existe, se busca por código normalizado
 *    (trim + mayúsculas) contra el catálogo de ambas empresas.
 *  - Si no aparece en ningún lado, queda sin idERP -> se marca "sin emparejar"
 *    en la vista (ver Product::isMatched()).
 */
class ProductErpMatcher
{
    public function __construct(private StockService $stock)
    {
    }

    /**
     * @return array{matched_before: int, matched_now: int, unmatched: int, total: int}
     */
    public function match(): array
    {
        $catalog = $this->stock->getProducts();

        $byCode = [];
        $existsByEmpresaId = [];

        foreach ($catalog as $item) {
            $codigo = $this->normalize($item['codigo'] ?? '');
            $empresa = $item['empresa_codigo'] ?? 'TRAMEC';
            $id = $item['id'] ?? null;

            if ($codigo !== '' && $id !== null) {
                // Si dos productos normalizan al mismo código, se queda con el primero.
                $byCode[$codigo] ??= ['id' => $id, 'empresa' => $empresa];
                $existsByEmpresaId["{$empresa}:{$id}"] = true;
            }
        }

        $stats = ['matched_before' => 0, 'matched_now' => 0, 'unmatched' => 0, 'total' => 0];

        Product::query()->orderBy('id')->chunk(200, function ($products) use (&$stats, $byCode, $existsByEmpresaId) {
            foreach ($products as $product) {
                $stats['total']++;

                if ($product->idERP && $product->idERP_empresa
                    && isset($existsByEmpresaId["{$product->idERP_empresa}:{$product->idERP}"])) {
                    $stats['matched_before']++;
                    continue;
                }

                $match = $byCode[$this->normalize($product->name)] ?? null;

                if ($match) {
                    $product->forceFill([
                        'idERP' => $match['id'],
                        'idERP_empresa' => $match['empresa'],
                    ])->save();
                    $stats['matched_now']++;
                    continue;
                }

                if ($product->idERP !== null || $product->idERP_empresa !== null) {
                    // Tenía un vínculo que ya no existe en el catálogo actual (el ERP lo
                    // borró/renumeró): mejor dejarlo en blanco (y marcado ⚠️) que mostrar
                    // un idERP que apunta a otro producto.
                    $product->forceFill(['idERP' => null, 'idERP_empresa' => null])->save();
                }

                $stats['unmatched']++;
            }
        });

        return $stats;
    }

    private function normalize(?string $value): string
    {
        return Str::of($value ?? '')->trim()->upper()->value();
    }
}
