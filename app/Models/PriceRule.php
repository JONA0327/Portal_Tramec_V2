<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Search;

class PriceRule extends Model
{
    // Mismas columnas que muestra Existencias (ver ExistenciasController).
    public const COLUMNAS = [
        'canal_tmc' => 'CANAL TMC',
        'canal' => 'CANAL',
        'oem' => 'OEM',
        'usuario_final' => 'USUARIO FINAL',
        'precio_lista' => 'Precio de Lista',
    ];

    // users.level del portal original (v1, admin/users/edit).
    public const NIVELES = [
        0 => 'ADMINISTRADOR',
        1 => 'EQUIPO TRAMEC',
        2 => 'CANAL REVENTA ESPECIAL',
        3 => 'CANAL REVENTA',
        4 => 'OEM',
        5 => 'USUARIO FINAL',
        6 => 'CIPSA',
        7 => 'EQUIPO TRAMEC ESPECIAL',
        8 => 'EQUIPO TRAMEC VENTAS 2',
        9 => 'CANAL ENTRANTE (BM, IIPSA, STM)',
    ];

    public const TIPOS = [
        'descuento' => 'Descuento (baja el precio)',
        'aumento' => 'Aumento (precio ÷ (1 − %))',
        'cero' => 'Ocultar precio (se muestra $0.00)',
        'temporal' => 'Precio temporal (usa el Precio 1 del producto)',
    ];

    // Precio temporal, igual que en el portal anterior (v1): el "Precio 1" (price1) del producto es el nuevo precio de lista.
    // DELTA: cada canal = price1 × estos factores. Otras marcas: el precio del canal × (price1 ÷ precio de lista actual).
    public const FACTORES_DELTA = ['canal_tmc' => 0.7275, 'canal' => 0.745, 'oem' => 0.812, 'usuario_final' => 0.854];

    private const CAMPO_PRECIO = ['canal_tmc' => 'price2', 'canal' => 'price3', 'oem' => 'price4', 'usuario_final' => 'price5'];

    protected $fillable = ['nombre', 'tipo', 'porcentaje', 'buscar', 'campo', 'incluidos', 'columnas', 'niveles', 'usuarios', 'usuarios_excluidos', 'activo'];

    protected function casts(): array
    {
        return [
            'porcentaje' => 'float',
            'columnas' => 'array',
            'niveles' => 'array',
            'usuarios' => 'array',
            'usuarios_excluidos' => 'array',
            'incluidos' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function visibleToLevel(int|string|null $level): bool
    {
        return empty($this->niveles) || ($level !== null && in_array((int) $level, array_map('intval', $this->niveles), true));
    }

    /**
     * Nivel Y usuario: si la regla lista usuarios, solo esos; si lista niveles, solo esos; ambos vacíos = todos.
     * Los usuarios en "excepciones" (usuarios_excluidos) nunca reciben la regla.
     */
    public function visibleTo(?User $user): bool
    {
        if ($user !== null && in_array($user->id, array_map('intval', $this->usuarios_excluidos ?? []), true)) {
            return false;
        }

        return $this->visibleToLevel($user?->level)
            && (empty($this->usuarios) || ($user !== null && in_array($user->id, array_map('intval', $this->usuarios), true)));
    }

    /** Coincide con el filtro de texto Y, si se eligieron productos a mano, está entre ellos. */
    public function matches(string $codigo, string $nombre): bool
    {
        return $this->matchesFilter($codigo, $nombre)
            && (empty($this->incluidos) || in_array(strtoupper(trim($codigo)), $this->incluidos, true));
    }

    /** Solo el filtro de texto (lo que lista la vista previa para elegir productos). */
    public function matchesFilter(string $codigo, string $nombre): bool
    {
        $terms = array_filter(array_map('trim', explode(',', (string) $this->buscar)));

        if ($terms === []) {
            return true;
        }

        foreach ($terms as $term) {
            $campos = match ($this->campo) { 'codigo' => [$codigo], 'nombre' => [$nombre], default => [$codigo, $nombre] };

            if (Search::all($term, ...$campos)) {
                return true;
            }
        }

        return false;
    }

    /** Precio temporal de una columna para un producto local; null si no tiene Precio 1 (o no se puede calcular). */
    public static function precioTemporal(?Product $p, string $columna): ?float
    {
        $nuevo = (float) ($p?->price1 ?? 0);
        $lista = (float) ($p?->list_price ?? 0);

        if ($nuevo <= 0 || $lista <= 0) {
            return null;
        }

        if ($columna === 'precio_lista') {
            return round($nuevo, 2);
        }

        if (strcasecmp((string) $p->brand, 'DELTA') === 0) {
            return round($nuevo * (self::FACTORES_DELTA[$columna] ?? 0), 2) ?: null;
        }

        $actual = (float) ($p->{self::CAMPO_PRECIO[$columna] ?? 'price2'} ?? 0);

        return $actual > 0 ? round($actual * $nuevo / $lista, 2) : null;
    }

    /** ¿Alcanza al usuario por nivel/usuario, sin contar las excepciones? (quien está exceptuado ve el precio original y el temporal). */
    public function alcanzaA(?User $user): bool
    {
        return $this->visibleToLevel($user?->level)
            && (empty($this->usuarios) || ($user !== null && in_array($user->id, array_map('intval', $this->usuarios), true)));
    }

    public function appliesTo(string $column, string $codigo, string $nombre): bool
    {
        return in_array($column, $this->columnas ?? [], true) && $this->matches($codigo, $nombre);
    }

    /**
     * Descuento: precio × (1 − %). Aumento: precio ÷ (1 − %), igual que en el portal anterior (v1):
     * con 3 % es precio ÷ 0.97, o sea un margen sobre el precio final y no un recargo de 3 %.
     */
    public function apply(float $price): float
    {
        // "Ocultar precio": el precio real no se ve (sale $0.00), para los productos que coincidan con la regla.
        if ($this->tipo === 'cero') {
            return 0.0;
        }

        $base = 1 - $this->porcentaje / 100;

        if ($this->tipo === 'descuento') {
            return $price * $base;
        }

        // Un aumento de 100 % o más no tiene sentido con esta fórmula (la validación ya lo impide): sin división entre 0.
        return $base > 0 ? $price / $base : $price;
    }
}
