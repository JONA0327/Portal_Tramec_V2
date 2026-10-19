<?php

namespace App\Http\Controllers;

use App\Models\PriceRule;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Descuentos y aumentos de precio que se aplican solo al MOSTRAR (ver
 * App\Services\PriceAdjuster); jamás tocan la tabla `products`. Solo el
 * ADMINISTRADOR (users.level 0) entra aquí -- ver el middleware en routes/web.php.
 */
class PriceRulesController extends Controller
{
    public function index(StockService $stock): View
    {
        $catalog = collect($stock->getProducts());
        $rules = PriceRule::orderBy('id')->get();

        $counts = $rules->mapWithKeys(fn (PriceRule $r) => [
            $r->id => $catalog->filter(fn (array $p) => $r->matches((string) ($p['codigo'] ?? ''), (string) ($p['nombre'] ?? '')))->count(),
        ]);

        return view('precios.index', ['rules' => $rules, 'counts' => $counts, 'totalCatalog' => $catalog->count()]);
    }

    public function create(): View
    {
        return view('precios.form', ['rule' => new PriceRule([
            'tipo' => 'descuento',
            'campo' => 'ambos',
            'columnas' => array_keys(PriceRule::COLUMNAS),
            'activo' => true,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        PriceRule::create($this->validated($request));

        return redirect()->route('price-rules.index')->with('status', 'Regla creada.');
    }

    public function edit(PriceRule $priceRule): View
    {
        return view('precios.form', ['rule' => $priceRule]);
    }

    public function update(Request $request, PriceRule $priceRule): RedirectResponse
    {
        $priceRule->update($this->validated($request));

        return redirect()->route('price-rules.index')->with('status', 'Regla actualizada.');
    }

    public function toggle(PriceRule $priceRule): RedirectResponse
    {
        $priceRule->update(['activo' => ! $priceRule->activo]);

        return back()->with('status', $priceRule->activo ? 'Regla activada.' : 'Regla desactivada.');
    }

    public function destroy(PriceRule $priceRule): RedirectResponse
    {
        $priceRule->delete();

        return redirect()->route('price-rules.index')->with('status', 'Regla eliminada.');
    }

    /** Qué productos del catálogo coinciden con el filtro, antes de guardar la regla. */
    public function preview(Request $request, StockService $stock): JsonResponse
    {
        $probe = new PriceRule([
            'buscar' => (string) $request->query('buscar', ''),
            'campo' => in_array($request->query('campo'), ['codigo', 'nombre'], true) ? $request->query('campo') : 'ambos',
        ]);

        $matches = collect($stock->getProducts())
            ->filter(fn (array $p) => $probe->matches((string) ($p['codigo'] ?? ''), (string) ($p['nombre'] ?? '')))
            ->values();

        return response()->json([
            'total' => $matches->count(),
            'items' => $matches->take(8)->map(fn (array $p) => ($p['codigo'] ?? '-').' — '.($p['nombre'] ?? ''))->all(),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(['descuento', 'aumento'])],
            'porcentaje' => ['required', 'numeric', 'between:0.01,100'],
            'buscar' => ['nullable', 'string', 'max:255'],
            'campo' => ['required', Rule::in(['codigo', 'nombre', 'ambos'])],
            'columnas' => ['required', 'array', 'min:1'],
            'columnas.*' => [Rule::in(array_keys(PriceRule::COLUMNAS))],
            'niveles' => ['nullable', 'array'],
            'niveles.*' => [Rule::in(array_keys(PriceRule::NIVELES))],
        ]);

        $data['activo'] = $request->boolean('activo');
        $data['niveles'] = array_map('intval', $data['niveles'] ?? []) ?: null;

        return $data;
    }
}
