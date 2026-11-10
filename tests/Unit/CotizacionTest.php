<?php

namespace Tests\Unit;

use App\Models\Cotizacion;
use App\Models\CotizacionItem;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class CotizacionTest extends TestCase
{
    private function item(string $moneda, float $cantidad, array $precios, ?float $manual = null): CotizacionItem
    {
        return (new CotizacionItem)->forceFill(['moneda' => $moneda, 'cantidad' => $cantidad, 'precios' => $precios, 'precio_manual' => $manual]);
    }

    public function test_totales_por_moneda_con_descuento_e_iva(): void
    {
        $c = (new Cotizacion)->forceFill(['precio_base' => 'precio_lista', 'descuento_pct' => 5, 'iva_pct' => 16]);
        // 2 x 5,248.17 (precio editado a mano) = 10,496.34 en USD; 3 x 100 (de la lista) en MXN
        $c->setRelation('items', collect([
            $this->item('USD', 2, ['precio_lista' => 9999], 5248.17),
            $this->item('MXN', 3, ['precio_lista' => 100]),
        ]));

        $g = $c->gruposPorMoneda();

        $this->assertSame(['MXN', 'USD'], array_keys($g), 'una cotización por moneda, sin mezclarlas');
        $this->assertEqualsWithDelta(10496.34, $g['USD']['subtotal'], 0.001);
        $this->assertEqualsWithDelta(524.82, $g['USD']['descuento'], 0.001);                       // 5 %
        $this->assertEqualsWithDelta(1595.44, $g['USD']['iva'], 0.001);                            // 16 % de (subtotal - descuento) = ejemplo KIMEX
        $this->assertEqualsWithDelta(11566.96, $g['USD']['total'], 0.011);
        $this->assertEqualsWithDelta(300.0, $g['MXN']['subtotal'], 0.001);
    }

    public function test_el_precio_editado_manda_y_si_no_sigue_a_la_columna(): void
    {
        $i = $this->item('MXN', 1, ['canal' => 50, 'precio_lista' => 80]);

        $this->assertSame(80.0, $i->precioUnitario('precio_lista'));
        $this->assertSame(50.0, $i->precioUnitario('canal'));
        $this->assertSame(0.0, $i->precioUnitario('oem'), 'columna sin precio = 0 (el cotizador lo marca como "Sin precio")');
        $this->assertSame(10.0, $this->item('MXN', 1, ['canal' => 50], 10.0)->precioUnitario('canal'));
    }

    public function test_quien_puede_cotizar(): void
    {
        $nivel = fn ($l) => (new User)->forceFill(['level' => $l])->puedeCotizar();

        foreach ([0, 1, 7, 8] as $l) {
            $this->assertTrue($nivel($l), "nivel $l (administrador / equipo Tramec)");
        }
        foreach ([2, 3, 4, 5, 6, 9, null] as $l) {
            $this->assertFalse($nivel($l), 'nivel '.var_export($l, true).' no cotiza');
        }
    }
}
