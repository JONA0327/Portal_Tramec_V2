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
}
