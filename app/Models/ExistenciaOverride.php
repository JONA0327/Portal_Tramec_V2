<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ajuste manual sobre una existencia (empresa+id_producto+id_almacen), separado
 * del caché del ERP (ver StockLevelService) -- nunca se mezclan, esta tabla
 * solo guarda lo que alguien tocó a mano desde el portal.
 */
class ExistenciaOverride extends Model
{
    protected $fillable = [
        'empresa',
        'id_producto',
        'id_almacen',
        'cantidad_manual',
        'cantidad_erp_al_editar',
        'oculto',
        'de_baja',
        'de_baja_at',
        'de_baja_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_manual' => 'decimal:2',
            'cantidad_erp_al_editar' => 'decimal:2',
            'oculto' => 'boolean',
            'de_baja' => 'boolean',
            'de_baja_at' => 'datetime',
        ];
    }

    public function key(): string
    {
        return "{$this->empresa}:{$this->id_producto}:{$this->id_almacen}";
    }

    /**
     * Hay conflicto cuando se editó una cantidad a mano y, desde entonces, el
     * ERP volvió a traer un valor distinto al que tenía en el momento de esa
     * edición (ver cantidad_erp_al_editar) -- significa que la ficha manual
     * quedó desactualizada respecto al ERP.
     */
    public function tieneConflicto(?float $cantidadErpActual): bool
    {
        if ($this->cantidad_manual === null || $this->cantidad_erp_al_editar === null || $cantidadErpActual === null) {
            return false;
        }

        return abs((float) $this->cantidad_erp_al_editar - $cantidadErpActual) > 0.001;
    }
}
