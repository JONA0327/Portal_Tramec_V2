<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $table = 'warehouses';

    // idERP / idERP_empresa / almacen_erp_id NO van acá a propósito: los pone
    // únicamente WarehouseErpMatcher, nunca un formulario (mismo criterio que
    // Product, ver App\Services\ProductErpMatcher).
    protected $fillable = [
        'name',
    ];

    public function isMatched(): bool
    {
        return $this->idERP !== null && ! empty($this->idERP_empresa);
    }
}
