<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
        10 => 'EXCLUSIVO PIEZAS PLÁSTICAS',
    ];

    protected $fillable = ['nombre', 'tipo', 'porcentaje', 'buscar', 'campo', 'columnas', 'niveles', 'usuarios', 'activo'];

    protected function casts(): array
    {
        return [
            'porcentaje' => 'float',
            'columnas' => 'array',
            'niveles' => 'array',
            'usuarios' => 'array',
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

    public function matches(string $codigo, string $nombre): bool
    {
        $terms = array_filter(array_map('trim', explode(',', (string) $this->buscar)));

        if ($terms === []) {
            return true;
        }

        foreach ($terms as $term) {
            if (($this->campo !== 'nombre' && Str::contains($codigo, $term, true))
                || ($this->campo !== 'codigo' && Str::contains($nombre, $term, true))) {
                return true;
            }
        }

        return false;
    }

    public function appliesTo(string $column, string $codigo, string $nombre): bool
    {
        return in_array($column, $this->columnas ?? [], true) && $this->matches($codigo, $nombre);
    }

    // ponytail: aumento = ×(1+%); v1 usaba ÷(1−%) (5% → +5.26%). Cambiar acá si negocio quiere la fórmula vieja.
    public function apply(float $price): float
    {
        $factor = $this->tipo === 'descuento' ? 1 - $this->porcentaje / 100 : 1 + $this->porcentaje / 100;

        return $price * $factor;
    }
}
