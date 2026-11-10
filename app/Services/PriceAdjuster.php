<?php

namespace App\Services;

use App\Models\PriceRule;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Aplica las PriceRule activas (las que ve el nivel del usuario logueado) a un
 * precio ya leído de la BD, solo para mostrarlo. Registrado como scoped (ver
 * AppServiceProvider): las reglas se leen una vez por request.
 * Varias reglas que coincidan se apilan en orden de creación.
 */
class PriceAdjuster
{
    private ?Collection $rules = null;

    private ?Collection $temporales = null;

    public function adjust(?float $price, string $column, string $codigo, string $nombre, ?Product $producto = null): ?float
    {
        if ($price === null) {
            return null;
        }

        foreach ($this->rules() as $rule) {
            if ($rule->appliesTo($column, $codigo, $nombre)) {
                // Precio temporal: sustituye el precio por el calculado con el Precio 1 (sin Precio 1, queda el de siempre).
                if ($rule->tipo === 'temporal') {
                    $price = PriceRule::precioTemporal($producto, $column) ?? $price;

                    continue;
                }

                $price = $rule->apply($price);
            }
        }

        return round($price, 2);
    }

    /**
     * @param  array<string, mixed>  $prices  columna => precio original
     * @return array{0: array<string, ?float>, 1: array<string, float>, 2: array<string, float>}
     *         [precios ajustados, originales de los que cambiaron, precios temporales para mostrar en verde]
     *         Los originales solo se devuelven al ADMINISTRADOR (para el tooltip); al resto nunca. Los temporales en verde
     *         solo los ven los usuarios exceptuados de la regla (los demás ya ven el temporal como su precio).
     */
    public function adjustAll(array $prices, string $codigo, string $nombre, ?Product $producto = null): array
    {
        $verOriginal = Auth::user()?->isAdmin() ?? false;
        $adjusted = $originals = $temporales = [];

        foreach ($prices as $column => $value) {
            $original = $value === null ? null : (float) $value;
            $adjusted[$column] = $this->adjust($original, $column, $codigo, $nombre, $producto);

            // Un precio en $0.00 ("Ocultar precio") no se marca con la línea azul: debe pasar desapercibido.
            if ($verOriginal && $original !== null && $adjusted[$column] !== 0.0 && $adjusted[$column] !== round($original, 2)) {
                $originals[$column] = $original;
            }

            foreach ($this->temporalesVerdes() as $rule) {
                $t = $rule->appliesTo($column, $codigo, $nombre) ? PriceRule::precioTemporal($producto, $column) : null;

                if ($t !== null) {
                    $temporales[$column] = $t;

                    break;
                }
            }
        }

        return [$adjusted, $originals, $temporales];
    }

    /** Reglas de precio temporal que este usuario ve en verde (junto al original): solo los exceptuados de la regla. */
    private function temporalesVerdes(): Collection
    {
        $user = Auth::user();

        return $this->temporales ??= PriceRule::where('activo', true)->where('tipo', 'temporal')->orderBy('id')->get()
            ->filter(fn (PriceRule $r) => $user !== null && in_array($user->id, array_map('intval', $r->usuarios_excluidos ?? []), true))
            ->values();
    }

    private function rules(): Collection
    {
        return $this->rules ??= PriceRule::where('activo', true)->orderBy('id')->get()
            ->filter(fn (PriceRule $r) => $r->visibleTo(Auth::user()))
            ->values();
    }
}
