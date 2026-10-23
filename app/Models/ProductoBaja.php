<?php

namespace App\Models;

use App\Support\Codigo;
use Illuminate\Database\Eloquent\Model;

/**
 * Producto dado de baja desde el portal (ver la migración producto_bajas). El portal lo ignora
 * aunque CONTPAQi lo siga devolviendo; se reactiva desde la página Bajas.
 */
class ProductoBaja extends Model
{
    protected $table = 'producto_bajas';

    protected $fillable = ['empresa', 'codigo', 'id_erp', 'nombre', 'dado_de_baja_por'];

    /** @var array<string, true>|null "EMPRESA:CODIGO" -> true, una consulta por request */
    private static ?array $claves = null;

    public static function normalizar(?string $codigo): string
    {
        return Codigo::normalizar($codigo);
    }

    /** ¿Ese producto (empresa + código) está de baja? Con empresa null basta con que el código esté en cualquiera. */
    public static function esBaja(?string $empresa, ?string $codigo): bool
    {
        $codigo = self::normalizar($codigo);

        if ($codigo === '') {
            return false;
        }

        self::$claves ??= static::query()->get(['empresa', 'codigo'])
            ->mapWithKeys(fn (self $b) => [strtoupper($b->empresa).':'.self::normalizar($b->codigo) => true])->all();

        if ($empresa === null) {
            return count(array_filter(array_keys(self::$claves), fn ($k) => str_ends_with($k, ':'.$codigo))) > 0;
        }

        return isset(self::$claves[strtoupper($empresa).':'.$codigo]) || isset(self::$claves['*:'.$codigo]);
    }

    public static function olvidarCache(): void
    {
        self::$claves = null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::olvidarCache());
        static::deleted(fn () => self::olvidarCache());
    }
}
