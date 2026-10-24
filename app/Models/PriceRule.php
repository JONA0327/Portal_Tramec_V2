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

    protected $fillable = ['nombre', 'tipo', 'porcentaje', 'buscar', 'campo', 'incluidos', 'columnas', 'niveles', 'usuarios', 'activo'];

    protected function casts(): array
    {
        return [
            'porcentaje' => 'float',
            'columnas' => 'array',
            'niveles' => 'array',
            'usuarios' => 'array',
            'incluidos' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function visibleToLevel(int|string|null $level): bool
    {
        return empty($this->niveles) || ($level !== null && in_array((int) $level, array_map('intval', $this->niveles), true));
    }

    /** Nivel Y usuario: si la regla lista usuarios, solo esos; si lista niveles, solo esos; ambos vacíos = todos. */
    public function visibleTo(?User $user): bool
    {
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
        $base = 1 - $this->porcentaje / 100;

        if ($this->tipo === 'descuento') {
            return $price * $base;
        }

        // Un aumento de 100 % o más no tiene sentido con esta fórmula (la validación ya lo impide): sin división entre 0.
        return $base > 0 ? $price / $base : $price;
    }
}
