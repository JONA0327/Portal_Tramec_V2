<?php

namespace App\Support;

/**
 * Clave de comparación para códigos de producto: mayúsculas y SIN espacios, para que "HP 20 HF" (como lo
 * tienen en el portal o en un Excel) coincida con "HP20HF" (como está en CONTPAQi). Solo sirve para
 * comparar; el código que se muestra no se modifica.
 */
class Codigo
{
    public static function normalizar(?string $codigo): string
    {
        return mb_strtoupper(preg_replace('/[\s\x{00A0}]+/u', '', (string) $codigo) ?? '');
    }
}
