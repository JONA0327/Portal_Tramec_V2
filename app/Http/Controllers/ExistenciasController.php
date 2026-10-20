<?php

namespace App\Http\Controllers;

use App\Models\ExistenciaOverride;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\PriceAdjuster;
use App\Services\StockLevelService;
use App\Services\StockService;
use App\Support\StockHealth;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Support\Search;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Existencias: cantidad de cada producto por almacén, en vivo desde
 * StockLevelService (caché + sync completo cada 15 min + webhook en tiempo
 * real, ver servicio-stock::_vigilar_existencias). Las cantidades son 100%
 * información del ERP -- PERO se puede guardar un ajuste manual por encima
 * (ver ExistenciaOverride), completamente separado del caché del ERP:
 *  - "Editar" guarda una cantidad manual + una FOTO de lo que decía el ERP
 *    en ese momento (cantidad_erp_al_editar).
 *  - Si el ERP vuelve a cambiar después de esa edición, la foto ya no
 *    coincide con el valor actual del caché -> eso ES la inconsistencia
 *    (ver inconsistencias()/ExistenciaOverride::tieneConflicto()). Ninguno
 *    de los dos valores se pisa solo: queda pendiente hasta que alguien
 *    decida (mantenerManual() o aceptarErp()).
 *  - "Ocultar" y "Dar de baja" son switches independientes de la cantidad:
 *    solo afectan qué se muestra en el portal, nunca tocan el ERP ni
 *    servicio-stock (a diferencia del "Bloquear" de Productos, que sí llama
 *    a la blacklist del API).
 *
 * También se muestran los precios por canal (tabla local `products`), de
 * solo lectura también, para tener todo junto en una sola vista.
 */
class ExistenciasController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request, StockLevelService $stockLevels, StockService $stock, PriceAdjuster $adjuster): View
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $empresa = trim((string) $request->query('empresa', ''));
        $almacen = trim((string) $request->query('almacen', ''));
        $estado = $request->query('estado', 'con_existencia');
        // Clasificación = campo "Tipo de producto" (products.kind_of_product), como en v1.
        $clasificacion = trim((string) $request->query('clasificacion', ''));
        $agrupar = $request->boolean('agrupar'); // un renglón por modelo, con sus almacenes aparte

        if (! $request->user()->isAdmin() && in_array($estado, ['ocultos', 'bajas'], true)) {
            $estado = 'con_existencia'; // ocultos y bajas son de administración
        }

        $productsByKey = collect($stock->getProducts())
            ->keyBy(fn (array $p) => ($p['empresa_codigo'] ?? 'TRAMEC').':'.$p['id']);

        // Nombres de almacén: en vivo; si servicio-stock no responde, se usa la última copia guardada.
        try {
            $warehouseNamesByKey = collect(['tramec', 'omec'])
                ->flatMap(function (string $empresaLower) {
                    $almacenes = Http::withToken(config('services.stock.key'))
                        ->baseUrl(config('services.stock.url'))
                        ->timeout(20)
                        ->get("/api/almacenes/{$empresaLower}")
                        ->throw()
                        ->json();

                    return collect($almacenes)->mapWithKeys(fn (array $a) => [
                        strtoupper($empresaLower).':'.$a['id'] => trim($a['nombre']),
                    ]);
                });
            Cache::forever('stock_service.warehouse_names', $warehouseNamesByKey->all());
        } catch (ConnectionException|RequestException $e) {
            StockHealth::down();
            $warehouseNamesByKey = collect(Cache::get('stock_service.warehouse_names', []));
        }

        // alias > nombre local > nombre crudo del ERP (ej. el almacén 0 de TRAMEC es "(Ninguno)" en
        // CONTPAQi pero "DELTA Schenker" acá; ver WarehouseErpMatcher).
        $warehouseAliasesByKey = Warehouse::query()
            ->whereNotNull('idERP_empresa')
            ->whereNotNull('almacen_erp_id')
            ->get()
            ->mapWithKeys(fn (Warehouse $w) => [
                strtoupper($w->idERP_empresa).':'.$w->almacen_erp_id => $w->alias ?: $w->name,
            ]);

        $localPricesByKey = Product::query()
            ->whereNotNull('idERP')->where('idERP', '!=', '')
            ->whereNotNull('idERP_empresa')
            ->get()
            ->keyBy(fn (Product $p) => strtoupper($p->idERP_empresa).':'.$p->idERP);

        $localPricesByName = Product::query()->get()->keyBy(fn (Product $p) => strtoupper(trim($p->name)));

        $overridesByKey = ExistenciaOverride::all()->keyBy->key();

        // Overrides con cantidad manual que el ERP no trae (Excel / sin nivel en el ERP): se
        // muestran igual, como un nivel con cantidad ERP en 0.
        $levels = collect($stockLevels->getLevels());
        $erpKeys = $levels->mapWithKeys(fn (array $l) => [($l['empresa_codigo'] ?? 'TRAMEC').':'.$l['id_producto'].':'.$l['id_almacen'] => true]);
        $soloManuales = $overridesByKey
            ->filter(fn (ExistenciaOverride $o) => $o->cantidad_manual !== null && ! $erpKeys->has($o->key()))
            ->map(fn (ExistenciaOverride $o) => [
                'empresa_codigo' => $o->empresa, 'id_producto' => $o->id_producto,
                'id_almacen' => $o->id_almacen, 'cantidad' => 0,
            ]);

        $rows = $levels->concat($soloManuales)->map(function (array $level) use ($adjuster, $productsByKey, $warehouseNamesByKey, $warehouseAliasesByKey, $localPricesByKey, $localPricesByName, $overridesByKey) {
            $empresaCodigo = $level['empresa_codigo'] ?? 'TRAMEC';
            $producto = $productsByKey->get("{$empresaCodigo}:{$level['id_producto']}");
            $almacenKey = "{$empresaCodigo}:{$level['id_almacen']}";
            $almacenNombre = $warehouseAliasesByKey->get($almacenKey) ?? $warehouseNamesByKey->get($almacenKey);
            $override = $overridesByKey->get("{$empresaCodigo}:{$level['id_producto']}:{$level['id_almacen']}");
            $precios = $localPricesByKey->get("{$empresaCodigo}:{$level['id_producto']}")
                ?? ($override?->codigo ? $localPricesByName->get(strtoupper(trim($override->codigo))) : null);
            $cantidadErp = (float) $level['cantidad'];
            $tieneManual = $override && $override->cantidad_manual !== null;

            $codigo = $producto['codigo'] ?? $override?->codigo ?? "#{$level['id_producto']}";
            $nombre = $producto['nombre'] ?? $override?->nombre ?? $precios?->name ?? '(producto no encontrado en el catálogo)';
            // Texto provisional que usaban las importaciones anteriores de Excel; ya se guarda "Sin descripción".
            if ($nombre === '(nuevo, cargado desde Excel)') {
                $nombre = 'Sin descripción';
            }
            // Descuentos/aumentos (PriceRule) solo para mostrar; products no se toca.
            [$precioFinal, $precioOriginal] = $adjuster->adjustAll([
                'canal_tmc' => $precios?->price2,
                'canal' => $precios?->price3,
                'oem' => $precios?->price4,
                'usuario_final' => $precios?->price5,
                'precio_lista' => $precios?->list_price,
            ], $codigo, $nombre);

            return [
                'empresa' => $empresaCodigo,
                'id_producto' => $level['id_producto'],
                'id_almacen' => $level['id_almacen'],
                'codigo' => $codigo,
                'nombre' => $nombre,
                'descripcion' => $precios?->description_short ?: $nombre, // descripción corta de products; si no hay, la del ERP
                'almacen' => $almacenNombre ?: "Almacén #{$level['id_almacen']}",
                'cantidad_erp' => $cantidadErp,
                'cantidad' => $tieneManual ? (float) $override->cantidad_manual : $cantidadErp,
                'tiene_manual' => $tieneManual,
                'conflicto' => $override ? $override->tieneConflicto($cantidadErp) : false,
                'oculto' => $override?->oculto ?? false,
                'de_baja' => $override?->de_baja ?? false,
                'moneda' => $precios?->base_currency ?: 'MXN',
                'clasificacion' => $precios?->kind_of_product ?: null,
                ...$precioFinal,
                'originales' => $precioOriginal,
            ];
        });

        // Conteos sobre el total sin filtrar, para que los avisos del header no
        // cambien según lo que se esté buscando en ese momento.
        $counts = [
            'ocultos' => $rows->where('oculto', true)->count(),
            'bajas' => $rows->where('de_baja', true)->count(),
            'conflictos' => $rows->where('conflicto', true)->count(),
        ];

        // Por defecto no se muestran los ocultos ni las bajas -- son sus propios
        // filtros de "estado" (ver el select en la vista), no se mezclan con el
        // resto de la lista activa.
        if ($estado === 'ocultos') {
            $filtered = $rows->where('oculto', true);
        } elseif ($estado === 'bajas') {
            $filtered = $rows->where('de_baja', true);
        } else {
            $filtered = $rows->where('oculto', false)->where('de_baja', false);

            if ($estado === 'con_existencia') {
                $filtered = $filtered->filter(fn (array $r) => $r['cantidad'] > 0);
            }
        }

        $empresas = $rows->pluck('empresa')->unique()->sort()->values();
        $almacenes = $rows->pluck('almacen')->unique()->sort()->values();

        if ($empresa !== '') {
            $filtered = $filtered->filter(fn (array $r) => $r['empresa'] === $empresa);
        }

        if ($almacen !== '') {
            $filtered = $filtered->filter(fn (array $r) => $r['almacen'] === $almacen);
        }

        if ($clasificacion !== '') {
            $filtered = $filtered->filter(fn (array $r) => $clasificacion === '__sin' ? $r['clasificacion'] === null : $r['clasificacion'] === $clasificacion);
        }

        if ($buscar !== '') {
            // Palabras sueltas, en cualquier orden ("10hp 215t" encuentra "MOTOR 10HP 4P 215T").
            $filtered = $filtered->filter(fn (array $r) => Search::all($buscar, $r['codigo'], $r['descripcion']));
        }

        $filtered = $filtered->sortBy([['codigo', 'asc'], ['almacen', 'asc']])->values();

        if ($agrupar) {
            // Un renglón por modelo (empresa + código): precios una sola vez y, aparte, cada almacén con su cantidad.
            $filtered = $filtered
                ->groupBy(fn (array $r) => $r['empresa'].'|'.strtoupper($r['codigo']))
                ->map(fn ($g) => $g->first() + [
                    'almacenes' => $g->map(fn (array $r) => [
                        'almacen' => $r['almacen'], 'cantidad' => $r['cantidad'], 'empresa' => $r['empresa'],
                        'id_producto' => $r['id_producto'], 'id_almacen' => $r['id_almacen'],
                        'tiene_manual' => $r['tiene_manual'], 'conflicto' => $r['conflicto'], 'de_baja' => $r['de_baja'],
                    ])->sortBy('almacen')->values()->all(),
                    'total' => $g->sum('cantidad'),
                ])
                ->values();
        }

        $page = $request->integer('page', 1);

        $paginated = new LengthAwarePaginator(
            $filtered->forPage($page, self::PER_PAGE),
            $filtered->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('existencias', [
            'levels' => $paginated,
            'agrupar' => $agrupar,
            'buscar' => $buscar,
            'empresa' => $empresa,
            'almacen' => $almacen,
            'estado' => $estado,
            'clasificacion' => $clasificacion,
            'clasificaciones' => Product::whereNotNull('kind_of_product')->where('kind_of_product', '!=', '')->distinct()->orderBy('kind_of_product')->pluck('kind_of_product'),
            'empresas' => $empresas,
            'almacenes' => $almacenes,
            'counts' => $counts,
            'lastSyncAt' => $stockLevels->lastSyncAt(),
        ]);
    }

    /**
     * Carga masiva desde Excel (hoja "Referencia | Disp") del almacén Schenker. Dos pasos: importar() lee y valida el archivo y guarda la vista
     * previa en sesión; confirmarImportacion() aplica. Si la referencia está en el
     * catálogo del ERP se guarda como ajuste manual de ese producto; si no, se crea
     * una existencia solo acá (no se toca `products`). Las existencias "nuevas" que
     * una importación anterior dejó en ese almacén y que ya no vienen en el archivo
     * se quitan (el archivo es la foto del almacén); los ajustes de productos del
     * ERP no se tocan porque no se distinguen de una edición manual.
     */
    public function importar(Request $request, StockService $stock): RedirectResponse
    {
        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx']]);

        // El Excel es solo del almacén DELTA Schenker (TRAMEC, almacén 0 de CONTPAQi): todo lo demás
        // viene del ERP, por eso no hay selector de almacén.
        $warehouse = Warehouse::where('idERP_empresa', 'tramec')->where('almacen_erp_id', 0)->first();
        abort_unless($warehouse, 422, 'No existe el almacén Schenker (TRAMEC, almacén 0).');

        try {
            [$filas, $omitidas] = $this->leerXlsx($request->file('archivo')->getRealPath());
        } catch (\Throwable $e) {
            return back()->withErrors(['archivo' => 'No se pudo leer el archivo: '.$e->getMessage()]);
        }

        if ($filas === []) {
            return back()->withErrors(['archivo' => 'No hay filas válidas. Se esperan las columnas Referencia (A) y Disp (B).']);
        }

        $empresa = strtoupper($warehouse->idERP_empresa);
        $almacen = (int) $warehouse->almacen_erp_id;
        $catalogo = $this->catalogoPorCodigo($stock, $empresa);

        $enCatalogo = array_filter($filas, fn ($f) => $catalogo->has(strtoupper($f[0])));
        $nuevas = array_values(array_map(fn ($f) => $f[0], array_diff_key($filas, $enCatalogo)));
        $seQuitan = ExistenciaOverride::where('empresa', $empresa)->where('id_almacen', $almacen)->whereNotNull('codigo')
            ->pluck('codigo')->filter(fn ($c) => ! array_key_exists(strtoupper($c), $filas))->values()->all();

        // Productos del catálogo que ya tenían cantidad en Schenker y ya no vienen en el archivo: el
        // archivo es la foto completa del almacén, así que se quitan.
        $idsEnArchivo = $catalogo->filter(fn ($p, $code) => array_key_exists($code, $filas))->pluck('id')->all();
        $codigoPorId = $catalogo->mapWithKeys(fn ($p) => [$p['id'] => $p['codigo']]);
        $seQuitanErp = ExistenciaOverride::where('empresa', $empresa)->where('id_almacen', $almacen)
            ->whereNull('codigo')->whereNotNull('cantidad_manual')->where('id_producto', '<', 1_000_000_000)
            ->whereNotIn('id_producto', $idsEnArchivo)
            ->pluck('id_producto')
            ->map(fn ($id) => ['id' => (int) $id, 'codigo' => $codigoPorId->get($id, "#{$id}")])
            ->values()->all();

        session(['import_excel' => [
            'empresa' => $empresa,
            'id_almacen' => $almacen,
            'almacen' => $warehouse->alias ?: $warehouse->name,
            'archivo' => $request->file('archivo')->getClientOriginalName(),
            'filas' => array_values($filas),
            'omitidas' => $omitidas,
            'en_catalogo' => count($enCatalogo),
            'nuevas' => $nuevas,
            'se_quitan' => $seQuitan,
            'se_quitan_erp' => $seQuitanErp,
        ]]);

        return redirect()->route('existencias.importar.revisar');
    }

    public function revisarImportacion(Request $request): View|RedirectResponse
    {
        $preview = $request->session()->get('import_excel');

        return $preview
            ? view('existencias.importar-revisar', ['p' => $preview])
            : redirect()->route('existencias')->withErrors(['archivo' => 'No hay una importación pendiente; subí el archivo de nuevo.']);
    }

    public function confirmarImportacion(Request $request, StockService $stock): RedirectResponse
    {
        $p = $request->session()->pull('import_excel');

        if (! $p) {
            return redirect()->route('existencias')->withErrors(['archivo' => 'La vista previa expiró; subí el archivo de nuevo.']);
        }

        $catalogo = $this->catalogoPorCodigo($stock, $p['empresa']);
        $coinciden = $nuevas = 0;

        DB::transaction(function () use ($p, $catalogo, &$coinciden, &$nuevas) {
            foreach ($p['filas'] as [$ref, $cantidad]) {
                $erp = $catalogo->get(strtoupper($ref));
                $where = $erp
                    ? ['empresa' => $p['empresa'], 'id_producto' => $erp['id'], 'id_almacen' => $p['id_almacen']]
                    : ['empresa' => $p['empresa'], 'id_almacen' => $p['id_almacen'], 'codigo' => $ref];

                $override = ExistenciaOverride::query()->firstOrNew($where);
                if (! $override->exists && ! $erp) {
                    // ponytail: id sintético por max(id); con imports concurrentes usar una secuencia.
                    $override->id_producto = 1_000_000_000 + ExistenciaOverride::max('id') + 1;
                    $override->nombre = 'Sin descripción';
                }
                $override->forceFill(['cantidad_manual' => $cantidad, 'updated_by' => Auth::id()])->save();
                $erp ? $coinciden++ : $nuevas++;
            }

            if ($p['se_quitan']) {
                ExistenciaOverride::where('empresa', $p['empresa'])->where('id_almacen', $p['id_almacen'])
                    ->whereIn('codigo', $p['se_quitan'])->delete();
            }

            // Productos del catálogo que ya no vienen: se borra el ajuste, salvo que esté oculto o dado
            // de baja (eso se conserva y solo se le quita la cantidad).
            foreach ($p['se_quitan_erp'] as ['id' => $id]) {
                $o = ExistenciaOverride::where(['empresa' => $p['empresa'], 'id_almacen' => $p['id_almacen'], 'id_producto' => $id])->first();
                if (! $o) {
                    continue;
                }
                ($o->oculto || $o->de_baja) ? $o->forceFill(['cantidad_manual' => null])->save() : $o->delete();
            }
        });

        return redirect()->route('existencias', ['almacen' => $p['almacen']])
            ->with('status', "Excel importado en {$p['almacen']}: {$coinciden} del catálogo, {$nuevas} nuevas solo en Existencias, ".(count($p['se_quitan']) + count($p['se_quitan_erp'])).' quitadas.');
    }

    public function cancelarImportacion(Request $request): RedirectResponse
    {
        $request->session()->forget('import_excel');

        return redirect()->route('existencias');
    }

    private function catalogoPorCodigo(StockService $stock, string $empresa)
    {
        return collect($stock->getProducts())
            ->filter(fn (array $p) => ($p['empresa_codigo'] ?? 'TRAMEC') === $empresa)
            ->keyBy(fn (array $p) => strtoupper(trim($p['codigo'])));
    }

    /**
     * @return array{0: array<string, array{string, float}>, 1: int} filas únicas [referencia, cantidad]
     *         (si se repite una referencia gana la última) y cuántas filas se omitieron por cantidad inválida.
     */
    private function leerXlsx(string $path): array
    {
        $zip = new \ZipArchive;
        $zip->open($path);
        $compartidas = [];
        if ($xml = $zip->getFromName('xl/sharedStrings.xml')) {
            foreach (simplexml_load_string($xml)->si as $si) {
                $compartidas[] = html_entity_decode(strip_tags($si->asXML()));
            }
        }
        $hoja = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $filas = [];
        $omitidas = 0;
        $n = 0;
        foreach ($hoja->sheetData->row as $row) {
            $vals = [];
            foreach ($row->c as $c) {
                $col = preg_replace('/\d+/', '', (string) $c['r']);
                $vals[$col] = ((string) $c['t']) === 's' ? ($compartidas[(int) $c->v] ?? '') : (string) $c->v;
            }
            $ref = trim($vals['A'] ?? '');
            if ($ref === '') {
                continue;
            }
            if (is_numeric($vals['B'] ?? null)) {
                $filas[strtoupper($ref)] = [$ref, (float) $vals['B']];
            } elseif ($n++ > 0) { // la primera fila con texto es el encabezado
                $omitidas++;
            }
        }

        return [$filas, $omitidas];
    }

    public function edit(string $empresa, int $idProducto, int $idAlmacen, StockLevelService $stockLevels, StockService $stock): View
    {
        $empresa = strtoupper($empresa);
        $cantidadErp = $this->cantidadActualDelErp($stockLevels, $empresa, $idProducto, $idAlmacen);
        $override = ExistenciaOverride::query()
            ->where(['empresa' => $empresa, 'id_producto' => $idProducto, 'id_almacen' => $idAlmacen])
            ->first();

        $producto = collect($stock->getProducts())
            ->first(fn (array $p) => ($p['empresa_codigo'] ?? 'TRAMEC') === $empresa && (int) $p['id'] === $idProducto);

        return view('existencias.edit', [
            'empresa' => $empresa,
            'idProducto' => $idProducto,
            'idAlmacen' => $idAlmacen,
            'codigo' => $producto['codigo'] ?? $override?->codigo ?? "#{$idProducto}",
            'nombre' => $producto['nombre'] ?? $override?->nombre ?? '(producto no encontrado en el catálogo)',
            'cantidadErp' => $cantidadErp,
            'cantidadManual' => $override?->cantidad_manual,
            'oculto' => $override?->oculto ?? false,
        ]);
    }

    public function update(Request $request, string $empresa, int $idProducto, int $idAlmacen, StockLevelService $stockLevels): RedirectResponse
    {
        $empresa = strtoupper($empresa);
        $data = $request->validate(['cantidad' => ['required', 'numeric', 'min:0']]);
        $cantidadErp = $this->cantidadActualDelErp($stockLevels, $empresa, $idProducto, $idAlmacen);

        $override = ExistenciaOverride::query()->firstOrNew([
            'empresa' => $empresa,
            'id_producto' => $idProducto,
            'id_almacen' => $idAlmacen,
        ]);
        $override->forceFill([
            'cantidad_manual' => $data['cantidad'],
            // Foto del ERP en este momento: si más adelante cambia, ESO es la
            // inconsistencia (ver ExistenciaOverride::tieneConflicto()).
            'cantidad_erp_al_editar' => $cantidadErp,
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()->route('existencias')->with('status', 'Cantidad actualizada manualmente.');
    }

    public function ocultar(string $empresa, int $idProducto, int $idAlmacen): RedirectResponse
    {
        $override = ExistenciaOverride::query()->firstOrNew([
            'empresa' => strtoupper($empresa),
            'id_producto' => $idProducto,
            'id_almacen' => $idAlmacen,
        ]);
        $override->forceFill(['oculto' => true, 'updated_by' => Auth::id()])->save();

        return back()->with('status', 'Fila ocultada del listado (no afecta al ERP).');
    }

    public function restaurar(string $empresa, int $idProducto, int $idAlmacen): RedirectResponse
    {
        ExistenciaOverride::query()
            ->where(['empresa' => strtoupper($empresa), 'id_producto' => $idProducto, 'id_almacen' => $idAlmacen])
            ->update(['oculto' => false, 'updated_by' => Auth::id()]);

        return back()->with('status', 'Fila restaurada al listado.');
    }

    public function confirmBaja(string $empresa, int $idProducto, int $idAlmacen, StockService $stock): View
    {
        $empresa = strtoupper($empresa);
        $producto = collect($stock->getProducts())
            ->first(fn (array $p) => ($p['empresa_codigo'] ?? 'TRAMEC') === $empresa && (int) $p['id'] === $idProducto);

        $override = ExistenciaOverride::query()
            ->where(['empresa' => $empresa, 'id_producto' => $idProducto, 'id_almacen' => $idAlmacen])
            ->first();

        return view('existencias.confirm-baja', [
            'empresa' => $empresa,
            'idProducto' => $idProducto,
            'idAlmacen' => $idAlmacen,
            'codigo' => $producto['codigo'] ?? $override?->codigo ?? "#{$idProducto}",
            'nombre' => $producto['nombre'] ?? $override?->nombre ?? '(producto no encontrado en el catálogo)',
        ]);
    }

    public function darDeBaja(Request $request, string $empresa, int $idProducto, int $idAlmacen): RedirectResponse
    {
        // Misma seguridad que Productos/Almacenes: hay que escribir "confirmar".
        $request->validate(['confirmacion' => ['required', 'string']]);

        if (strtolower(trim($request->input('confirmacion'))) !== 'confirmar') {
            return back()
                ->withErrors(['confirmacion' => 'Tenés que escribir la palabra "confirmar" para dar de baja esta existencia.'])
                ->withInput();
        }

        $override = ExistenciaOverride::query()->firstOrNew([
            'empresa' => strtoupper($empresa),
            'id_producto' => $idProducto,
            'id_almacen' => $idAlmacen,
        ]);
        $override->forceFill([
            'de_baja' => true,
            'de_baja_at' => now(),
            'de_baja_by' => Auth::id(),
        ])->save();

        return redirect()->route('existencias')->with('status', 'Existencia dada de baja.');
    }

    public function inconsistencias(StockLevelService $stockLevels, StockService $stock): View
    {
        $productsByKey = collect($stock->getProducts())
            ->keyBy(fn (array $p) => ($p['empresa_codigo'] ?? 'TRAMEC').':'.$p['id']);

        $cantidadesActuales = collect($stockLevels->getLevels())
            ->keyBy(fn (array $l) => ($l['empresa_codigo'] ?? 'TRAMEC').':'.$l['id_producto'].':'.$l['id_almacen']);

        $pendientes = ExistenciaOverride::query()
            ->whereNotNull('cantidad_manual')
            ->whereNotNull('cantidad_erp_al_editar')
            ->get()
            ->map(function (ExistenciaOverride $o) use ($productsByKey, $cantidadesActuales) {
                $nivel = $cantidadesActuales->get($o->key());
                $cantidadActual = $nivel ? (float) $nivel['cantidad'] : null;
                $producto = $productsByKey->get("{$o->empresa}:{$o->id_producto}");

                return [
                    'override' => $o,
                    'codigo' => $producto['codigo'] ?? "#{$o->id_producto}",
                    'nombre' => $producto['nombre'] ?? '(producto no encontrado en el catálogo)',
                    'cantidad_actual_erp' => $cantidadActual,
                    'conflicto' => $o->tieneConflicto($cantidadActual),
                ];
            })
            ->filter(fn (array $r) => $r['conflicto'])
            ->values();

        return view('existencias.inconsistencias', ['pendientes' => $pendientes]);
    }

    /**
     * El ERP volvió a cambiar después de la edición manual, pero se decide
     * mantener el valor manual de todas formas: se actualiza la "foto" al
     * valor actual del ERP para que deje de marcarse como pendiente (hasta
     * que el ERP cambie de nuevo).
     */
    public function mantenerManual(ExistenciaOverride $override, StockLevelService $stockLevels): RedirectResponse
    {
        $cantidadErp = $this->cantidadActualDelErp($stockLevels, $override->empresa, $override->id_producto, $override->id_almacen);
        $override->forceFill(['cantidad_erp_al_editar' => $cantidadErp, 'updated_by' => Auth::id()])->save();

        return back()->with('status', 'Se mantiene la cantidad manual.');
    }

    /**
     * Se descarta el ajuste manual: de acá en más vuelve a mostrarse la
     * cantidad tal cual la trae el ERP.
     */
    public function aceptarErp(ExistenciaOverride $override): RedirectResponse
    {
        $override->forceFill([
            'cantidad_manual' => null,
            'cantidad_erp_al_editar' => null,
            'updated_by' => Auth::id(),
        ])->save();

        return back()->with('status', 'Se aceptó la cantidad del ERP.');
    }

    private function cantidadActualDelErp(StockLevelService $stockLevels, string $empresa, int $idProducto, int $idAlmacen): ?float
    {
        $nivel = collect($stockLevels->getLevels())
            ->first(fn (array $l) => ($l['empresa_codigo'] ?? 'TRAMEC') === $empresa
                && (int) $l['id_producto'] === $idProducto
                && (int) $l['id_almacen'] === $idAlmacen);

        return $nivel ? (float) $nivel['cantidad'] : null;
    }
}
