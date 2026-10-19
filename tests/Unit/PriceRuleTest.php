<?php

namespace Tests\Unit;

use App\Models\PriceRule;
use PHPUnit\Framework\TestCase;

class PriceRuleTest extends TestCase
{
    private function rule(array $attrs): PriceRule
    {
        return new PriceRule($attrs + ['tipo' => 'descuento', 'porcentaje' => 10, 'campo' => 'ambos', 'columnas' => ['canal']]);
    }

    public function test_aplica_descuento_y_aumento(): void
    {
        $this->assertEqualsWithDelta(90.0, $this->rule([])->apply(100), 0.001);
        $this->assertEqualsWithDelta(105.0, $this->rule(['tipo' => 'aumento', 'porcentaje' => 5])->apply(100), 0.001);
    }

    public function test_filtra_por_codigo_o_descripcion_con_varios_terminos(): void
    {
        $r = $this->rule(['buscar' => 'c2000, delta']);

        $this->assertTrue($r->matches('ABC', 'Motor C2000 Plus'));
        $this->assertTrue($r->matches('DELTA-1', 'Otro'));
        $this->assertFalse($r->matches('XYZ', 'Nada'));
        $this->assertFalse($this->rule(['buscar' => 'delta', 'campo' => 'codigo'])->matches('XYZ', 'Delta motor'));
        $this->assertTrue($this->rule(['buscar' => ''])->matches('X', 'Y'));
    }

    public function test_solo_afecta_las_columnas_elegidas(): void
    {
        $r = $this->rule([]);

        $this->assertTrue($r->appliesTo('canal', 'A', 'B'));
        $this->assertFalse($r->appliesTo('oem', 'A', 'B'));
    }

    public function test_niveles(): void
    {
        $this->assertTrue($this->rule([])->visibleToLevel(5));
        $r = $this->rule(['niveles' => [3, 4]]);
        $this->assertTrue($r->visibleToLevel('3'));
        $this->assertFalse($r->visibleToLevel(0));
        $this->assertFalse($r->visibleToLevel(null));
    }
}
