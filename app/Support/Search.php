<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Búsqueda por palabras: cada palabra de la consulta debe aparecer en algún campo,
 * en cualquier orden ("10hp 215t" encuentra "MOTOR 10HP 4P 215T"). Misma regla que
 * el filtro en vivo de precios/form.blade.php (función coincide()).
 */
class Search
{
    public static function all(string $query, ?string ...$fields): bool
    {
        $haystack = implode(' ', array_map('strval', $fields));

        foreach (preg_split('/\s+/', trim($query), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            if (! Str::contains($haystack, $word, true)) {
                return false;
            }
        }

        return true;
    }
}
