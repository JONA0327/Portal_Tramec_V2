<?php

namespace App\Http\Controllers;

use App\Models\ProductoBaja;
use App\Models\User;
use App\Services\StockService;
use App\Support\PorPagina;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Productos dados de baja (solo administradores). La baja es del portal: CONTPAQi no se toca y,
 * aunque lo siga devolviendo, el portal lo ignora (no sale como "nuevo"). Aquí se listan y se reactivan.
 */
class BajasController extends Controller
{
    private const PER_PAGE = 25; // predeterminado; el usuario puede elegir 10/25/50/100 (ver PorPagina)

    public function index(Request $request, StockService $stock): View
    {
        $buscar = trim((string) $request->query('buscar', ''));

        $enCatalogo = collect($stock->getProducts())
            ->mapWithKeys(fn (array $p) => [strtoupper($p['empresa_codigo'] ?? 'TRAMEC').':'.ProductoBaja::normalizar($p['codigo'] ?? '') => $p]);

        $bajas = ProductoBaja::query()->orderByDesc('created_at')->get()
            ->map(function (ProductoBaja $b) use ($enCatalogo) {
                $b->sigue_en_erp = $enCatalogo->has(strtoupper($b->empresa).':'.ProductoBaja::normalizar($b->codigo));

                return $b;
            })
            ->when($buscar !== '', fn ($c) => $c->filter(fn (ProductoBaja $b) => Search::all($buscar, $b->codigo, $b->nombre)))
            ->values();

        $page = max(1, $request->integer('page', 1));
        $perPage = PorPagina::desde($request, self::PER_PAGE);
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $bajas->forPage($page, $perPage),
            $bajas->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('bajas', [
            'bajas' => $paginated,
            'perPage' => $perPage,
            'buscar' => $buscar,
            'usuarios' => User::whereIn('id', $paginated->pluck('dado_de_baja_por')->filter())->get()->keyBy('id'),
        ]);
    }

    public function store(Request $request, StockService $stock): RedirectResponse
    {
        $data = $request->validate([
            'empresa' => ['nullable', 'string', 'max:10'],
            'codigo' => ['required', 'string', 'max:100'],
            'id_erp' => ['nullable', 'integer'],
        ]);

        $empresa = strtoupper(trim((string) ($data['empresa'] ?? ''))) ?: '*';
        $codigo = ProductoBaja::normalizar($data['codigo']);

        $producto = collect($stock->getProducts())->first(fn (array $p) => strtoupper($p['empresa_codigo'] ?? 'TRAMEC') === $empresa
            && ProductoBaja::normalizar($p['codigo'] ?? '') === $codigo);

        ProductoBaja::firstOrCreate(
            ['empresa' => $empresa, 'codigo' => $codigo],
            ['id_erp' => $data['id_erp'] ?? ($producto['id'] ?? null), 'nombre' => $producto['nombre'] ?? null, 'dado_de_baja_por' => $request->user()->id]
        );

        return back()->with('status', "{$codigo} dado de baja. Puedes reactivarlo desde Bajas.");
    }

    public function reactivar(ProductoBaja $baja): RedirectResponse
    {
        $codigo = $baja->codigo;
        $baja->delete();

        return back()->with('status', "{$codigo} reactivado: vuelve a Productos y Existencias.");
    }
}
