<?php

namespace Tests\Unit;

use App\Models\Product;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    public function test_canal_precio_factor_delta_por_nivel(): void
    {
        $delta = (new Product)->forceFill(['brand' => 'DELTA', 'list_price' => 800, 'price3' => 600]);

        // Sin nivel, u otro nivel cualquiera (ej. 3, CANAL REVENTA): precio normal de la columna "canal".
        $this->assertEqualsWithDelta(600.0, $delta->canalPrecio(null), 0.001);
        $this->assertEqualsWithDelta(600.0, $delta->canalPrecio(3), 0.001);

        // CIPSA (6) y canal entrante (9) tienen su propio factor fijo sobre list_price, SIEMPRE
        // (no depende de ninguna regla de precio ni usa price1 — eso es solo "Precio temporal").
        $this->assertEqualsWithDelta(527.44, $delta->canalPrecio(6), 0.001); // 800 * 0.6593
        $this->assertEqualsWithDelta(516.0, $delta->canalPrecio(9), 0.001);  // 800 * 0.645
    }

    public function test_canal_precio_no_aplica_a_otras_marcas(): void
    {
        $otra = (new Product)->forceFill(['brand' => 'MARATHON', 'list_price' => 800, 'price3' => 600]);

        $this->assertEqualsWithDelta(600.0, $otra->canalPrecio(6), 0.001);
        $this->assertEqualsWithDelta(600.0, $otra->canalPrecio(9), 0.001);
    }

    public function test_canal_precio_sin_list_price_cae_al_precio_normal(): void
    {
        $sinLista = (new Product)->forceFill(['brand' => 'DELTA', 'price3' => 600]);

        $this->assertEqualsWithDelta(600.0, $sinLista->canalPrecio(6), 0.001);
    }
}
