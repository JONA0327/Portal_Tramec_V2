<?php

namespace Tests\Unit;

use App\Models\PriceRule;
use App\Models\User;
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
        $this->assertEqualsWithDelta(970.0, $this->rule(['porcentaje' => 3])->apply(1000), 0.001); // descuento 3 % => precio x 0.97, sin cambios
        $this->assertEqualsWithDelta(0.0, $this->rule(['porcentaje' => 100])->apply(500), 0.001);    // descuento 100 % => gratis, como antes
        $this->assertEqualsWithDelta(100 / 0.95, $this->rule(['tipo' => 'aumento', 'porcentaje' => 5])->apply(100), 0.001);
        $this->assertEqualsWithDelta(1000 / 0.97, $this->rule(['tipo' => 'aumento', 'porcentaje' => 3])->apply(1000), 0.001); // 3 % => precio / 0.97
        $this->assertEqualsWithDelta(500.0, $this->rule(['tipo' => 'aumento', 'porcentaje' => 100])->apply(500), 0.001); // sin división entre 0
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

    public function test_busca_por_palabras_en_cualquier_orden(): void
    {
        $this->assertTrue($this->rule(['buscar' => '10hp 215t'])->matches('10HP 4P 215T F', 'MOTOR 10HP 4P 215T 230/460 TEFC'));
        $this->assertTrue($this->rule(['buscar' => '215t motor'])->matches('X', 'MOTOR 10HP 4P 215T'));
        $this->assertFalse($this->rule(['buscar' => '10hp 999'])->matches('X', 'MOTOR 10HP 4P 215T'));
        $this->assertFalse($this->rule(['buscar' => 'motor', 'campo' => 'codigo'])->matches('10HP', 'MOTOR 10HP'));
    }

    public function test_solo_afecta_las_columnas_elegidas(): void
    {
        $r = $this->rule([]);

        $this->assertTrue($r->appliesTo('canal', 'A', 'B'));
        $this->assertFalse($r->appliesTo('oem', 'A', 'B'));
    }

    public function test_incluidos_limitan_la_regla_a_los_elegidos(): void
    {
        $r = $this->rule(['buscar' => 'ah', 'incluidos' => ['AH04AD-5A']]);

        $this->assertTrue($r->matches(' ah04ad-5a ', 'Variador'));
        $this->assertFalse($r->matches('AH04DA-5A', 'Variador'));
        $this->assertFalse($r->matches('XYZ', 'Otro')); // elegido o no, debe pasar el filtro de texto
        $this->assertTrue($this->rule(['buscar' => 'ah'])->matches('AH04DA-5A', 'Variador')); // sin elegidos = todos
    }

    public function test_usuarios_especificos(): void
    {
        $r = $this->rule(['usuarios' => [7]]);
        $a = (new User)->forceFill(['id' => 7, 'level' => 3]);
        $b = (new User)->forceFill(['id' => 8, 'level' => 3]);

        $this->assertTrue($r->visibleTo($a));
        $this->assertFalse($r->visibleTo($b));
        $this->assertFalse($r->visibleTo(null));
        $this->assertTrue($this->rule([])->visibleTo($b));
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
