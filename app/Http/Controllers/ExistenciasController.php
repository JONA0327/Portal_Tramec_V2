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
use Illuminate\Support\Facades\Http;
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

        $warehouseAliasesByKey = Warehouse::query()
            ->whereNotNull('idERP_empresa')
            ->whereNotNull('almacen_erp_id')
            ->whereNotNull('alias')
            ->get()
            ->mapWithKeys(fn (Warehouse $w) => [
                strtoupper($w->idERP_empresa).':'.$w->almacen_erp_id => $w->alias,
            ]);

        $localPricesByKey = Product::query()
            ->whereNotNull('idERP')->where('idERP', '!=', '')
            ->whereNotNull('idERP_empresa')
            ->get()
            ->keyBy(fn (Product $p) => strtoupper($p->idERP_empresa).':'.$p->idERP);

        $overridesByKey = ExistenciaOverride::all()->keyBy->key();

        $rows = collect($stockLevels->getLevels())->map(function (array $level) use ($adjuster, $productsByKey, $warehouseNamesByKey, $warehouseAliasesByKey, $localPricesByKey, $overridesByKey) {
            $empresaCodigo = $level['empresa_codigo'] ?? 'TRAMEC';
            $producto = $productsByKey->get("{$empresaCodigo}:{$level['id_producto']}");
            $almacenKey = "{$empresaCodigo}:{$level['id_almacen']}";
            $almacenNombre = $warehouseAliasesByKey->get($almacenKey) ?? $warehouseNamesByKey->get($almacenKey);
            $precios = $localPricesByKey->get("{$empresaCodigo}:{$level['id_producto']}");

            $override = $overridesByKey->get("{$empresaCodigo}:{$level['id_producto']}:{$level['id_almacen']}");
            $cantidadErp = (float) $level['cantidad'];
            $tieneManual = $override && $override->cantidad_manual !== null;

            $codigo = $producto['codigo'] ?? "#{$level['id_producto']}";
            $nombre = $producto['nombre'] ?? '(producto no encontrado en el catálogo)';
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
                'almacen' => $almacenNombre ?: "Almacén #{$level['id_almacen']}",
                'cantidad_erp' => $cantidadErp,
                'cantidad' => $tieneManual ? (float) $override->cantidad_manual : $cantidadErp,
                'tiene_manual' => $tieneManual,
                'conflicto' => $override ? $override->tieneConflicto($cantidadErp) : false,
                'oculto' => $override?->oculto ?? false,
                'de_baja' => $override?->de_baja ?? false,
                'moneda' => $precios?->base_currency ?: 'MXN',
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

        if ($buscar !== '') {
            $filtered = $filtered->filter(function (array $r) use ($buscar) {
                return Str::contains($r['codigo'], $buscar, true)
                    || Str::contains($r['nombre'], $buscar, true);
            });
        }

        $filtered = $filtered->sortBy([['codigo', 'asc'], ['almacen', 'asc']])->values();
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
            'buscar' => $buscar,
            'empresa' => $empresa,
            'almacen' => $almacen,
            'estado' => $estado,
            'empresas' => $empresas,
            'almacenes' => $almacenes,
            'counts' => $counts,
            'lastSyncAt' => $stockLevels->lastSyncAt(),
        ]);
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
            'codigo' => $producto['codigo'] ?? "#{$idProducto}",
            'nombre' => $producto['nombre'] ?? '(producto no encontrado en el catálogo)',
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

        return view('existencias.confirm-baja', [
            'empresa' => $empresa,
            'idProducto' => $idProducto,
            'idAlmacen' => $idAlmacen,
            'codigo' => $producto['codigo'] ?? "#{$idProducto}",
            'nombre' => $producto['nombre'] ?? '(producto no encontrado en el catálogo)',
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
