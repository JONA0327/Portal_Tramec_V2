<?php

namespace App\Models;

use App\Support\Codigo;
use Illuminate\Database\Eloquent\Model;

/**
 * Estado propio del portal sobre una existencia (empresa+id_producto+id_almacen), separado del
 * caché del ERP (ver StockLevelService): ocultar / dar de baja, y las cantidades que NO vienen
 * del ERP (almacén Schenker, cargado desde Excel).
 *
 * Las cantidades de lo que está ligado al ERP vienen siempre de CONTPAQi y no se editan desde el
 * portal: cualquier cantidad guardada aquí para un producto+almacén que el ERP reporta se descarta
 * (ver reconciliarConErp()).
 */
class ExistenciaOverride extends Model
{
    protected $fillable = [
        'empresa',
        'id_producto',
        'id_almacen',
        'codigo',
        'nombre',
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
     * Las referencias del Excel de Schenker que no están en CONTPAQi se guardan con un id sintético
     * (>= 1e9) y su código. Cuando esa referencia se da de alta en CONTPAQi, aquí se liga al producto
     * real (mismo código, misma empresa): la ficha pasa a su id del ERP y desde entonces toma nombre,
     * descripción y precios del catálogo, sin duplicarse ni perder la cantidad.
     *
     * @param  iterable<array>  $productos  catálogo del ERP (cada uno con id, codigo, empresa_codigo)
     * @return int fichas ligadas
     */
    public static function ligarConCatalogo(iterable $productos): int
    {
        $ids = [];
        foreach ($productos as $p) {
            $ids[($p['empresa_codigo'] ?? 'TRAMEC').':'.Codigo::normalizar($p['codigo'] ?? '')] = (int) $p['id'];
        }

        $ligadas = 0;

        static::whereNotNull('codigo')->where('id_producto', '>=', 1_000_000_000)->get()
            ->each(function (self $o) use ($ids, &$ligadas) {
                $id = $ids[$o->empresa.':'.Codigo::normalizar($o->codigo)] ?? null;

                if (! $id) {
                    return; // sigue sin existir en el ERP
                }

                $ligadas++;
                $real = static::where(['empresa' => $o->empresa, 'id_producto' => $id, 'id_almacen' => $o->id_almacen])->first();

                if (! $real) {
                    $o->forceFill(['id_producto' => $id, 'codigo' => null, 'nombre' => null])->save();

                    return;
                }

                // Ya había ficha del producto real: se queda esa, con la cantidad más reciente y las marcas de ambas.
                $real->forceFill([
                    'cantidad_manual' => $o->updated_at > $real->updated_at ? $o->cantidad_manual : $real->cantidad_manual,
                    'oculto' => $real->oculto || $o->oculto,
                    'de_baja' => $real->de_baja || $o->de_baja,
                ])->save();
                $o->delete();
            });

        return $ligadas;
    }

    /**
     * El ERP prevalece: descarta toda cantidad guardada para un producto+almacén que CONTPAQi
     * reporta. Se conservan las marcas de oculto / de baja y las existencias que el ERP no trae
     * (Schenker, por Excel).
     *
     * @param  array<string, float>  $erp  cantidad del ERP por "EMPRESA:id_producto:id_almacen"
     * @return int cantidades descartadas
     */
    public static function reconciliarConErp(array $erp): int
    {
        if ($erp === []) {
            return 0;
        }

        $descartados = 0;

        static::whereNotNull('cantidad_manual')->where('id_producto', '<', 1_000_000_000)->get()
            ->each(function (self $o) use ($erp, &$descartados) {
                if (! array_key_exists($o->key(), $erp)) {
                    return; // el ERP no reporta este par: la cantidad viene del Excel
                }

                $descartados++;

                if ($o->oculto || $o->de_baja || $o->codigo !== null) {
                    $o->forceFill(['cantidad_manual' => null, 'cantidad_erp_al_editar' => null])->save();
                } else {
                    $o->delete();
                }
            });

        return $descartados;
    }
}
