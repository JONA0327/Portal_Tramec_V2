<?php

namespace App\Services;

use App\Models\PriceRule;
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

    public function adjust(?float $price, string $column, string $codigo, string $nombre): ?float
    {
        if ($price === null) {
            return null;
        }

        foreach ($this->rules() as $rule) {
            if ($rule->appliesTo($column, $codigo, $nombre)) {
                $price = $rule->apply($price);
            }
        }

        return round($price, 2);
    }

    /**
     * @param  array<string, mixed>  $prices  columna => precio original
     * @return array{0: array<string, ?float>, 1: array<string, float>}  [precios ajustados, originales de los que cambiaron]
     *         Los originales solo se devuelven al ADMINISTRADOR (para el tooltip); al resto nunca.
     */
    public function adjustAll(array $prices, string $codigo, string $nombre): array
    {
        $verOriginal = Auth::user()?->isAdmin() ?? false;
        $adjusted = $originals = [];

        foreach ($prices as $column => $value) {
            $original = $value === null ? null : (float) $value;
            $adjusted[$column] = $this->adjust($original, $column, $codigo, $nombre);

            if ($verOriginal && $original !== null && $adjusted[$column] !== round($original, 2)) {
                $originals[$column] = $original;
            }
        }

        return [$adjusted, $originals];
    }

    private function rules(): Collection
    {
        return $this->rules ??= PriceRule::where('activo', true)->orderBy('id')->get()
            ->filter(fn (PriceRule $r) => $r->visibleToLevel(Auth::user()?->level))
            ->values();
    }
}
