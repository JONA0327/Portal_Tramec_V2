<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductoBaja;
use App\Services\BlacklistService;
use App\Services\PriceAdjuster;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\Search;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Vista unificada del catálogo: mezcla el catálogo en vivo del ERP (StockService,
 * ambas empresas) con la tabla local de precios (`products`), vinculados por
 * idERP + idERP_empresa (ver ProductErpMatcher para cómo se arma ese vínculo).
 *
 * Tres estados por fila:
 *  - normal:      existe en el ERP y tiene precio local cargado.
 *  - nuevo:       existe en el ERP pero todavía no se le cargó precio localmente.
 *  - advertencia: hay un precio local cargado pero no se encontró ese producto
 *                 en el catálogo actual del ERP (código descontinuado/cambiado).
 *
 * El nombre/código de un producto "normal" o "advertencia" con precio local lo
 * mantiene al día el ERP automáticamente (ver App\Listeners\SyncLocalProductFromErp);
 * acá nunca se edita a mano.
 */
class ProductsController extends Controller
{
    private const PER_PAGE = 10;

    /** @return array<string, mixed> los 5 precios de un producto local, con las mismas claves que PriceRule::COLUMNAS. */
    private function preciosDe(?Product $p): array
    {
        return [
            'canal_tmc' => $p?->price2,
            'canal' => $p?->price3,
            'oem' => $p?->price4,
            'usuario_final' => $p?->price5,
            'precio_lista' => $p?->list_price,
        ];
    }

    public function index(Request $request, StockService $stock, PriceAdjuster $adjuster): View
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $empresa = trim((string) $request->query('empresa', ''));
        $estado = trim((string) $request->query('estado', ''));
        // Clasificación = campo "Tipo de producto" (products.kind_of_product), como en v1.
        $clasificacion = trim((string) $request->query('clasificacion', ''));

        // Filtra productos "de única ocasión" (servicios/personalizaciones que se dan
        // de alta al facturar, sin ser catálogo real) — ver isOneOffErpItem(). No se
        // borra nada de la base ni de servicio-stock, solo se dejan de mostrar acá.
        $catalog = collect($stock->getProducts())
            ->reject(fn (array $item) => $this->isOneOffErpItem($item))
            // Dados de baja desde el portal: se ignoran aunque CONTPAQi los siga devolviendo (no salen como "nuevos").
            ->reject(fn (array $item) => ProductoBaja::esBaja($item['empresa_codigo'] ?? 'TRAMEC', $item['codigo'] ?? ''));

        $empresas = $catalog
            ->map(fn (array $item) => $item['empresa_codigo'] ?? 'TRAMEC')
            ->unique()
            ->sort()
            ->values();

        $localByKey = Product::query()
            ->whereNotNull('idERP')->where('idERP', '!=', '')
            ->whereNotNull('idERP_empresa')
            ->get()
            ->keyBy(fn (Product $p) => "{$p->idERP_empresa}:{$p->idERP}");

        $rows = $catalog->map(function (array $item) use ($localByKey, $adjuster) {
            $empresaCodigo = $item['empresa_codigo'] ?? 'TRAMEC';
            $local = $localByKey->get("{$empresaCodigo}:{$item['id']}");
            [$precios, $originales] = $adjuster->adjustAll($this->preciosDe($local), $item['codigo'] ?? '-', $item['nombre'] ?? '-');

            return [
                'erp_id' => $item['id'] ?? null,
                'erp_empresa' => $empresaCodigo,
                'codigo' => $item['codigo'] ?? '-',
                'nombre' => $item['nombre'] ?? '-',
                'descripcion' => $local?->description_short ?: ($item['nombre'] ?? '-'),
                'empresa' => $empresaCodigo,
                'local_id' => $local?->id,
                'clasificacion' => $local?->kind_of_product ?: null,
                'precios' => $precios,
                'originales' => $originales,
                'moneda' => $local?->base_currency ?: 'MXN',
                'estado' => $local ? 'normal' : 'nuevo',
            ];
        });

        $unmatchedLocal = Product::query()
            ->where(function ($q) {
                $q->whereNull('idERP')->orWhere('idERP', '')->orWhereNull('idERP_empresa');
            })
            ->get()
            ->reject(fn (Product $p) => ProductoBaja::esBaja(null, $p->name))
            ->map(function (Product $p) use ($adjuster) {
                [$precios, $originales] = $adjuster->adjustAll($this->preciosDe($p), (string) $p->name, (string) $p->name2);

                return [
                'erp_id' => null,
                'erp_empresa' => null,
                'codigo' => $p->name,
                'nombre' => $p->name2,
                'descripcion' => $p->description_short ?: $p->name2,
                'empresa' => null,
                'local_id' => $p->id,
                'clasificacion' => $p->kind_of_product ?: null,
                'precios' => $precios,
                'originales' => $originales,
                'moneda' => $p->base_currency ?: 'MXN',
                'estado' => 'advertencia',
                ];
            });

        $allRows = $rows->concat($unmatchedLocal);

        // Niveles de cliente (2 a 6): solo productos ya registrados (con precio), sin contadores ni
        // filtro de estado: nuevos / sin emparejar son avisos internos de catálogo.
        $cliente = $request->user()->esCliente();
        if ($cliente) {
            $allRows = $allRows->where('estado', 'normal');
            $estado = '';
        }

        // Conteos sobre el total sin filtrar, para que los avisos no cambien según
        // lo que se esté buscando en ese momento.
        $counts = [
            'nuevo' => $allRows->where('estado', 'nuevo')->count(),
            'advertencia' => $allRows->where('estado', 'advertencia')->count(),
        ];

        $filtered = $allRows;

        if ($empresa !== '') {
            $filtered = $filtered->filter(fn (array $r) => $r['empresa'] === $empresa);
        }

        if ($estado !== '') {
            $filtered = $filtered->filter(fn (array $r) => $r['estado'] === $estado);
        }

        if ($clasificacion !== '') {
            $filtered = $filtered->filter(fn (array $r) => $clasificacion === '__sin' ? $r['clasificacion'] === null : $r['clasificacion'] === $clasificacion);
        }

        if ($buscar !== '') {
            // Palabras sueltas, en cualquier orden ("10hp 215t" encuentra "MOTOR 10HP 4P 215T").
            $filtered = $filtered->filter(fn (array $r) => Search::all($buscar, $r['codigo'], $r['descripcion']));
        }

        $filtered = $filtered->values();
        $page = $request->integer('page', 1);

        $paginated = new LengthAwarePaginator(
            $filtered->forPage($page, self::PER_PAGE),
            $filtered->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('products', [
            'products' => $paginated,
            'buscar' => $buscar,
            'empresa' => $empresa,
            'estado' => $estado,
            'clasificacion' => $clasificacion,
            'clasificaciones' => Product::whereNotNull('kind_of_product')->where('kind_of_product', '!=', '')->distinct()->orderBy('kind_of_product')->pluck('kind_of_product'),
            'empresas' => $empresas,
            'counts' => $counts,
            'cliente' => $cliente,
            'lastSyncAt' => $stock->lastSyncAt(),
        ]);
    }

    /**
     * Registra un producto "nuevo" (existe en el ERP, todavía no tiene fila en la
     * tabla de precios) con un solo clic: crea el registro local ya vinculado al
     * idERP, sin pedir nada más. Los precios se cargan después con "Editar" — el
     * nombre/código no hace falta pedirlos acá porque los toma del ERP y los
     * mantiene al día solo de ahí en más (ver App\Listeners\SyncLocalProductFromErp).
     */
    public function register(Request $request, StockService $stock): RedirectResponse
    {
        $data = $request->validate([
            'erp_empresa' => ['required', 'string'],
            'erp_id' => ['required', 'integer'],
        ]);

        // empresa_codigo del ERP y products.idERP_empresa van en MAYÚSCULAS (TRAMEC / OMEC); solo la API
        // de blacklist usa minúsculas (ver block()). Antes se buscaba en minúsculas y nunca coincidía: 404.
        $empresa = strtoupper($data['erp_empresa']);
        $erpProduct = $this->findErpProduct($stock, $empresa, $data['erp_id']);
        abort_unless($erpProduct, 404, 'Ese producto ya no existe en el catálogo del ERP.');

        // idERP/idERP_empresa no están en $fillable a propósito (ver Product::$fillable),
        // así que Product::create() los descartaría en silencio: se ponen con forceFill().
        $product = new Product();
        $product->forceFill([
            'name' => $erpProduct['codigo'],
            'name2' => preg_replace('/[^A-Za-z0-9]/', '', $erpProduct['codigo']),
            'idERP' => $erpProduct['id'],
            'idERP_empresa' => $empresa,
        ]);
        $product->save();

        return back()->with('status', "Producto {$erpProduct['codigo']} registrado. Cargale el precio cuando quieras con \"Editar\".");
    }

    /**
     * Bloquea un producto "nuevo" (servicio/personalización de única ocasión que
     * no debería mostrarse) vía el blacklist de servicio-stock (ver
     * BlacklistService). Es reversible desde ahí mismo (DELETE /api/blacklist/{empresa}),
     * esto no borra nada, solo lo oculta.
     */
    public function block(Request $request, BlacklistService $blacklist, StockService $stock): RedirectResponse
    {
        $data = $request->validate([
            'erp_empresa' => ['required', 'string'],
            'erp_id' => ['required', 'integer'],
        ]);

        try {
            $blacklist->add(strtolower($data['erp_empresa']), $data['erp_id']);

            // Sync inmediato para que desaparezca ya de la lista, sin esperar los 15 min
            // del programador de tareas.
            $stock->sync();
        } catch (\Illuminate\Http\Client\ConnectionException|\Illuminate\Http\Client\RequestException $e) {
            \App\Support\StockHealth::down();

            return back()->with('error', 'El servicio de existencias no responde; no se pudo bloquear el producto. Intentá de nuevo más tarde.');
        }

        return redirect()->route('products')->with('status', 'Producto bloqueado: ya no va a volver a aparecer.');
    }

    public function show(Product $product): View
    {
        return view('products.show', ['product' => $product]);
    }

    public function edit(Product $product): View
    {
        return view('products.edit', ['product' => $product]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        // name/name2 no están acá: los mantiene al día el ERP (ver
        // App\Listeners\SyncLocalProductFromErp), nunca se editan a mano.
        $product->update($this->validatePricingFields($request));

        return redirect()->route('products.show', $product)->with('status', 'Producto actualizado.');
    }

    /**
     * Detecta productos "de única ocasión": se dan de alta en CONTPAQi al momento
     * de facturar algo puntual (un servicio, una personalización) y no representan
     * catálogo real con existencia. No hay (todavía) un campo del ERP que los marque
     * de forma confiable — mientras tanto se filtra por el único patrón que se
     * verificó limpio: el nombre dice explícitamente que es un servicio/personalización
     * (16 casos confirmados, ej. "SERVICIO DE INSTALACIÓN...", "SERVICIO DE PINTURA...").
     *
     * Se probó también excluir por código puramente numérico corto (1, 02, 0001...),
     * pero esa regla agarraba ~1,042 productos, y varios de la muestra eran
     * motorreductores reales con specs completas (ej. "0001 MOTORREDUCTOR UMI40 5
     * 0.18KW..."), no basura de facturación — demasiado riesgo de esconder catálogo
     * real, así que NO se aplica todavía. Si se confirma el campo real del ERP que
     * distingue "servicio/sin inventario" de "producto", reemplazar esto por eso.
     */
    private function isOneOffErpItem(array $item): bool
    {
        $nombre = mb_strtoupper($item['nombre'] ?? '');

        foreach (['SERVICIO', 'PERSONALIZACION', 'PERSONALIZACIÓN'] as $palabra) {
            if (str_contains($nombre, $palabra)) {
                return true;
            }
        }

        return false;
    }

    private function findErpProduct(StockService $stock, string $empresa, mixed $id): ?array
    {
        return collect($stock->getProducts())
            ->first(fn (array $item) => strtoupper($item['empresa_codigo'] ?? 'TRAMEC') === strtoupper($empresa)
                && (string) ($item['id'] ?? '') === (string) $id);
    }

    private function validatePricingFields(Request $request): array
    {
        $data = $request->validate([
            'description_short' => ['nullable', 'string', 'max:191'],
            'description_supplier' => ['nullable', 'string', 'max:300'],
            'brand' => ['nullable', 'string', 'max:80'],
            'kind_of_product' => ['nullable', 'string', 'max:191'],
            'clasification_code' => ['nullable', 'integer'],
            'active' => ['nullable', 'boolean'],
            'base_currency' => ['nullable', 'string', 'max:50'],
            'rotation' => ['nullable', 'string', 'max:50'],
            'characteristic1' => ['nullable', 'string', 'max:50'],
            'characteristic2' => ['nullable', 'string', 'max:50'],
            'characteristic3' => ['nullable', 'string', 'max:50'],
            'characteristic4' => ['nullable', 'string', 'max:50'],
            'characteristic5' => ['nullable', 'string', 'max:50'],
            'list_price' => ['nullable', 'numeric'],
            'price1' => ['nullable', 'numeric'],
            'price2' => ['nullable', 'numeric'],
            'price3' => ['nullable', 'numeric'],
            'price4' => ['nullable', 'numeric'],
            'price5' => ['nullable', 'numeric'],
        ]);

        // "active" no viene en el POST cuando el checkbox está desmarcado.
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
