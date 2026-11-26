<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionItem extends Model
{
    protected $fillable = ['cotizacion_id', 'empresa', 'codigo', 'descripcion', 'moneda', 'precios', 'precio_manual', 'cantidad', 'unidad', 'tiempo_entrega', 'canal'];

    protected function casts(): array
    {
        return ['precios' => 'array', 'precio_manual' => 'float', 'cantidad' => 'float'];
    }

    /** Precio unitario: el editado a mano; si no, el de la columna (canal) del renglón o, si no tiene, la general. */
    public function precioUnitario(string $columna): float
    {
        $columna = $this->canal ?: $columna;

        if ($this->precio_manual !== null) {
            return (float) $this->precio_manual;
        }

        return (float) ($this->precios[$columna] ?? 0);
    }

    /** Los campos de precio de este renglón convertidos a pesos (precio unitario redondeado a centavos). */
    public function enPesos(float $tipoCambio): array
    {
        $convertir = fn ($v) => $v === null ? null : round((float) $v * $tipoCambio, 2);

        return [
            'moneda' => 'MXN',
            'precios' => array_map($convertir, $this->precios ?? []),
            'precio_manual' => $convertir($this->precio_manual),
        ];
    }

    public function importe(string $columna): float
    {
        return round($this->precioUnitario($columna) * (float) $this->cantidad, 2);
    }
}
