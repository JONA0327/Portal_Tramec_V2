<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';

    // idERP / idERP_empresa NO van acá a propósito: los pone únicamente el
    // comando de reconciliación (ver ProductErpMatcher), nunca un formulario.
    protected $fillable = [
        'name',
        'name2',
        'description_short',
        'description_supplier',
        'brand',
        'kind_of_product',
        'clasification_code',
        'active',
        'base_currency',
        'rotation',
        'characteristic1',
        'characteristic2',
        'characteristic3',
        'characteristic4',
        'characteristic5',
        'list_price',
        'price1',
        'price2',
        'price3',
        'price4',
        'price5',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'list_price' => 'decimal:2',
            'price1' => 'decimal:2',
            'price2' => 'decimal:2',
            'price3' => 'decimal:2',
            'price4' => 'decimal:2',
            'price5' => 'decimal:2',
        ];
    }

    public function isMatched(): bool
    {
        return ! empty($this->idERP) && ! empty($this->idERP_empresa);
    }

    /**
     * Precio base de la columna "canal" (price3) para quien lo ve con este nivel. CIPSA (6) y canal
     * entrante (9) tienen su propio factor fijo sobre list_price para DELTA (ver
     * PriceRule::FACTORES_DELTA_NIVEL); todos los demás niveles y marcas ven el price3 normal, como
     * siempre. Esto es la base: PriceAdjuster todavía puede aplicarle descuentos/aumentos encima.
     */
    public function canalPrecio(?int $nivel): ?float
    {
        $factor = $nivel !== null ? (PriceRule::FACTORES_DELTA_NIVEL[$nivel] ?? null) : null;

        if ($factor !== null && strcasecmp((string) $this->brand, 'DELTA') === 0 && $this->list_price !== null) {
            return round((float) $this->list_price * $factor, 2);
        }

        return $this->price3 !== null ? (float) $this->price3 : null;
    }
}
