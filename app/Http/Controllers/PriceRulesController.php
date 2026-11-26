<?php

namespace App\Http\Controllers;

use App\Models\PriceRule;
use App\Models\Product;
use App\Models\User;
use App\Services\StockService;
use App\Support\Codigo;
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
    /** Catálogo del ERP con la descripción que ven los usuarios (products.description_short; si no hay, el nombre del ERP). */
    private function catalogo(StockService $stock): \Illuminate\Support\Collection
    {
        $descripciones = Product::query()->whereNotNull('idERP')->where('idERP', '!=', '')->whereNotNull('idERP_empresa')
            ->get(['idERP', 'idERP_empresa', 'description_short', 'brand'])
            ->mapWithKeys(fn (Product $p) => [strtoupper($p->idERP_empresa).':'.$p->idERP => $p]);

        return collect($stock->getProducts())->map(function (array $p) use ($descripciones) {
            $local = $descripciones->get(strtoupper($p['empresa_codigo'] ?? 'TRAMEC').':'.($p['id'] ?? ''));
            $p['nombre'] = $local?->description_short ?: ($p['nombre'] ?? '');
            $p['marca'] = $local?->brand ?: null;

            return $p;
        });
    }

    /** Marcas disponibles para el filtro (products.brand, en mayúsculas). */
    private function marcas(): \Illuminate\Support\Collection
    {
        return Product::whereNotNull('brand')->where('brand', '!=', '')->pluck('brand')->map(fn ($b) => mb_strtoupper(trim($b)))->unique()->sort()->values();
    }

    public function index(StockService $stock): View
    {
        $catalog = $this->catalogo($stock);
        $rules = PriceRule::orderBy('orden')->orderBy('id')->get();

        $counts = $rules->mapWithKeys(fn (PriceRule $r) => [
            $r->id => $catalog->filter(fn (array $p) => $r->matchesMarca($p['marca'] ?? null) && $r->matches((string) ($p['codigo'] ?? ''), (string) ($p['nombre'] ?? '')))->count(),
        ]);

        return view('precios.index', ['usuariosPorId' => User::whereIn('id', $rules->pluck('usuarios')->merge($rules->pluck('usuarios_excluidos'))->flatten()->filter()->unique())->get()->keyBy('id'), 'rules' => $rules, 'counts' => $counts, 'totalCatalog' => $catalog->count()]);
    }

    public function create(StockService $stock): View
    {
        return view('precios.form', ['catalogo' => $this->previewItems($stock), 'users' => User::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email']), 'marcas' => $this->marcas(), 'rule' => new PriceRule([
            'tipo' => 'descuento',
            'campo' => 'ambos',
            'columnas' => array_keys(PriceRule::COLUMNAS),
            'activo' => true,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $precios1 = $data['precios1'];
        unset($data['precios1']);

        $rule = PriceRule::create($data);
        // La nueva entra encima de las reglas menos específicas (usuario > nivel > todos); luego se puede subir o bajar.
        $lista = PriceRule::whereKeyNot($rule->id)->orderBy('orden')->orderBy('id')->get();
        $posicion = $lista->search(fn (PriceRule $r) => $r->especificidad() < $rule->especificidad());
        $lista->splice($posicion === false ? $lista->count() : $posicion, 0, [$rule]);
        $this->numerar($lista);
        $this->sincronizarPrecios1($rule, [], $precios1);

        return redirect()->route('price-rules.index')->with('status', 'Regla creada.');
    }

    public function edit(PriceRule $priceRule, StockService $stock): View
    {
        return view('precios.form', ['catalogo' => $this->previewItems($stock), 'users' => User::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email']), 'marcas' => $this->marcas(), 'rule' => $priceRule]);
    }

    public function update(Request $request, PriceRule $priceRule): RedirectResponse
    {
        $data = $this->validated($request);
        $precios1 = $data['precios1'];
        unset($data['precios1']);

        $antes = $priceRule->tipo === 'temporal' ? ($priceRule->incluidos ?? []) : [];
        $priceRule->update($data);
        $this->sincronizarPrecios1($priceRule, $antes, $precios1);

        return redirect()->route('price-rules.index')->with('status', 'Regla actualizada.');
    }

    /** Sube o baja una regla un lugar: gana la que esté más arriba. */
    public function mover(PriceRule $priceRule, string $direccion): RedirectResponse
    {
        $lista = PriceRule::orderBy('orden')->orderBy('id')->get()->values();
        $i = $lista->search(fn (PriceRule $r) => $r->is($priceRule));
        $j = $direccion === 'subir' ? $i - 1 : $i + 1;

        if ($j >= 0 && $j < $lista->count()) {
            [$lista[$i], $lista[$j]] = [$lista[$j], $lista[$i]];
            $this->numerar($lista);
        }

        return back()->with('status', 'Orden de las reglas actualizado.');
    }

    private function numerar(\Illuminate\Support\Collection $lista): void
    {
        $lista->values()->each(fn (PriceRule $r, int $i) => $r->update(['orden' => $i + 1]));
    }

    public function toggle(PriceRule $priceRule): RedirectResponse
    {
        $priceRule->update(['activo' => ! $priceRule->activo]);

        return back()->with('status', $priceRule->activo ? 'Regla activada.' : 'Regla desactivada.');
    }

    public function destroy(PriceRule $priceRule): RedirectResponse
    {
        // Regla de precio temporal eliminada: los productos pierden su Precio 1 (salvo los que sigue usando otra regla).
        if ($priceRule->tipo === 'temporal') {
            $this->limpiarPrecio1($priceRule->incluidos ?? [], $priceRule->id);
        }

        $priceRule->delete();

        return redirect()->route('price-rules.index')->with('status', 'Regla eliminada.');
    }

    /** Qué productos del catálogo coinciden con el filtro, antes de guardar la regla. */
    public function preview(Request $request, StockService $stock): JsonResponse
    {
        return response()->json($this->previewItems($stock, (string) $request->query('buscar', ''), (string) $request->query('campo', 'ambos')));
    }

    /**
     * Lista para la vista previa del formulario. Va incrustada en la página (create/edit) para no
     * hacer una segunda petición al entrar: así llega junto con el HTML y comprimida.
     */
    private function previewItems(StockService $stock, string $buscar = '', string $campo = 'ambos'): array
    {
        $probe = new PriceRule([
            'buscar' => $buscar,
            'campo' => in_array($campo, ['codigo', 'nombre'], true) ? $campo : 'ambos',
        ]);

        $matches = $this->catalogo($stock)
            ->filter(fn (array $p) => $probe->matchesFilter((string) ($p['codigo'] ?? ''), (string) ($p['nombre'] ?? '')))
            ->unique(fn (array $p) => strtoupper(trim($p['codigo'] ?? '')))
            ->sortBy(fn (array $p) => strtoupper($p['codigo'] ?? ''))
            ->values();

        // Precio de lista y Precio 1 (price1) actuales de cada producto registrado en el portal, para la regla de precio temporal.
        $locales = Product::query()->get(['name', 'list_price', 'price1', 'base_currency'])
            ->reverse()->keyBy(fn (Product $p) => Codigo::normalizar($p->name));

        return [
            'total' => $matches->count(),
            'items' => $matches->take(3000)->map(function (array $p) use ($locales) {
                $codigo = trim($p['codigo'] ?? '-');
                $l = $locales->get(Codigo::normalizar($codigo));

                return ['c' => $codigo, 'm' => mb_strtoupper((string) ($p['marca'] ?? '')), 'n' => $p['nombre'] ?? '', 'l' => $l ? (float) $l->list_price : null, 'p' => $l && $l->price1 > 0 ? (float) $l->price1 : null];
            })->values()->all(),
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(array_keys(PriceRule::TIPOS))],
            'porcentaje' => [Rule::excludeIf(in_array($request->input('tipo'), ['cero', 'temporal', 'fija_lista'], true)), 'required', 'numeric', 'between:0.01,100'],
            'buscar' => ['nullable', 'string', 'max:255'],
            'campo' => ['required', Rule::in(['codigo', 'nombre', 'ambos'])],
            'columnas' => ['required', 'array', 'min:1'],
            'columnas.*' => [Rule::in(array_keys(PriceRule::COLUMNAS))],
            'niveles' => ['nullable', 'array'],
            'niveles.*' => [Rule::in(array_keys(PriceRule::NIVELES))],
            'marcas' => ['nullable', 'array'],
            'marcas.*' => ['string', 'max:100'],
            'incluidos' => ['nullable', 'array', 'max:5000'],
            'incluidos.*' => ['string', 'max:100'],
            'precios1' => ['nullable', 'array', 'max:5000'],
            'precios1.*.c' => ['required', 'string', 'max:100'],
            'precios1.*.v' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'usuarios' => ['nullable', 'array'],
            'usuarios.*' => ['integer', 'exists:users,id'],
            'usuarios_excluidos' => ['nullable', 'array'],
            'usuarios_excluidos.*' => ['integer', 'exists:users,id'],
        ]);

        $data['porcentaje'] = in_array($data['tipo'], ['cero', 'temporal', 'fija_lista'], true) ? 0 : $data['porcentaje'];

        if ($data['tipo'] === 'aumento' && (float) $data['porcentaje'] >= 100) {
            throw \Illuminate\Validation\ValidationException::withMessages(['porcentaje' => 'Un aumento debe ser menor a 100 % (se calcula como precio ÷ (1 − %)).']);
        }

        $data['precios1'] = array_values($data['precios1'] ?? []);

        if ($data['tipo'] === 'temporal' && empty($data['incluidos'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['incluidos' => 'Con Precio temporal elige los productos que se van a cambiar (marca las casillas de la lista).']);
        }

        if ($data['tipo'] === 'fija_lista' && empty($data['incluidos'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['incluidos' => 'Con Precio de lista en los canales elige los productos específicos a los que aplica (marca las casillas de la lista).']);
        }

        $data['activo'] = $request->boolean('activo');
        $data['niveles'] = array_map('intval', $data['niveles'] ?? []) ?: null;
        $data['usuarios'] = array_map('intval', $data['usuarios'] ?? []) ?: null;
        $data['usuarios_excluidos'] = array_map('intval', $data['usuarios_excluidos'] ?? []) ?: null;
        $data['marcas'] = array_values(array_unique(array_map(fn ($b) => mb_strtoupper(trim($b)), $data['marcas'] ?? []))) ?: null;
        $data['incluidos'] = array_values(array_unique(array_map(fn ($c) => strtoupper(trim($c)), $data['incluidos'] ?? []))) ?: null;

        return $data;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Product> productos locales cuyo código (sin espacios, mayúsculas) está en la lista */
    private function productosPorCodigo(array $codigos)
    {
        $claves = array_values(array_unique(array_map(fn ($c) => Codigo::normalizar($c), $codigos)));

        return $claves === [] ? Product::query()->whereRaw('1 = 0')->get()
            : Product::query()->whereIn(\Illuminate\Support\Facades\DB::raw("UPPER(REPLACE(name, ' ', ''))"), $claves)->get();
    }

    /**
     * Precio temporal: lo capturado en la regla se guarda como Precio 1 (price1) de cada producto elegido (vacío = sin
     * Precio 1). Los productos que salen de la regla (se desmarcan o cambia el tipo) pierden su Precio 1.
     */
    private function sincronizarPrecios1(PriceRule $rule, array $incluidosAntes, array $precios1): void
    {
        $ahora = $rule->tipo === 'temporal' ? array_map('strval', $rule->incluidos ?? []) : [];
        $this->limpiarPrecio1(array_diff($incluidosAntes, $ahora), $rule->id);

        foreach ($precios1 as $fila) {
            $codigo = (string) ($fila['c'] ?? '');

            if (! in_array(mb_strtoupper(trim($codigo)), $ahora, true)) {
                continue;
            }

            $valor = (float) ($fila['v'] ?? 0);
            $this->productosPorCodigo([$codigo])->each(fn (Product $p) => $p->update(['price1' => $valor > 0 ? $valor : null]));
        }
    }

    /** Quita el Precio 1 de esos códigos, salvo los que otra regla de precio temporal sigue incluyendo. */
    private function limpiarPrecio1(array $codigos, int $exceptoRegla): void
    {
        if ($codigos === []) {
            return;
        }

        $enOtras = PriceRule::where('tipo', 'temporal')->where('id', '!=', $exceptoRegla)->pluck('incluidos')->flatten()
            ->map(fn ($c) => Codigo::normalizar($c))->all();

        $libres = array_filter($codigos, fn ($c) => ! in_array(Codigo::normalizar($c), $enOtras, true));
        $this->productosPorCodigo($libres)->each(fn (Product $p) => $p->update(['price1' => null]));
    }
}
