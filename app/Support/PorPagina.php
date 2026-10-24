<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * "Mostrar N registros" de las tablas con paginación (como el selector de DataTables de v1).
 * Se pide con ?por_pagina=; solo se aceptan las opciones de OPCIONES, cualquier otro valor usa el predeterminado.
 */
class PorPagina
{
    public const OPCIONES = [10, 25, 50, 100];

    public static function desde(Request $request, int $predeterminado): int
    {
        $n = $request->integer('por_pagina');

        return in_array($n, self::OPCIONES, true) ? $n : $predeterminado;
    }
}
