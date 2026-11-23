<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExistenciaOverride;
use App\Models\Product;
use App\Models\ProductoBaja;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PriceAdjuster;
use App\Services\StockLevelService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * API para terceros, con las mismas rutas y el mismo formato de respuesta que la del portal v1
 * ({success, data, message}). El precio que se devuelve es el de lista ya ajustado por las reglas de precio del
 * usuario dueño del token (igual que lo ve en el portal).
 */
class ApiController extends Controller
{
    private const CAMPOS_EXTERNO = ['id', 'name', 'description_supplier', 'brand', 'kind_of_product', 'base_currency', 'list_price'];

    private function ok(mixed $data, string $message = 'OK'): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => $message]);
    }

    private function error(string $message, int $code = 404): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $code);
    }

    public function login(Request $request): JsonResponse
    {
        $user = User::where('email', (string) $request->input('username'))->first();

        if (! $user || (int) $user->active_flag !== 1 || ! Hash::check((string) $request->input('password'), $user->password)) {
            return $this->error('No autorizado.', 401);
        }

        $token = Str::random(60);
        DB::table('api_tokens')->where('user_id', $user->id)->where('nombre', 'login')->delete();
        DB::table('api_tokens')->insert(['user_id' => $user->id, 'nombre' => 'login', 'token' => hash('sha256', $token), 'created_at' => now(), 'updated_at' => now()]);

        return $this->ok(['token' => $token, 'level' => $user->level], 'User login successfully.');
    }

    // ---- Productos (cliente externo) ----

    public function products(PriceAdjuster $adj): JsonResponse
    {
        return $this->productos(Product::query(), $adj, false);
    }

    public function productById(PriceAdjuster $adj, int $id): JsonResponse
    {
        return $this->uno(Product::query()->whereKey($id), $adj, false);
    }

    public function productByName(PriceAdjuster $adj, string $name): JsonResponse
    {
        return $this->uno(Product::query()->where('name', $name), $adj, false);
    }

    public function productsByBrand(PriceAdjuster $adj, string $brand): JsonResponse
    {
        return $this->productos(Product::query()->where('brand', $brand), $adj, false);
    }

    public function productListPrice(PriceAdjuster $adj, int $id): JsonResponse
    {
        return $this->uno(Product::query()->whereKey($id), $adj, false, ['id', 'list_price']);
    }

    // ---- Productos (equipo TMC: más campos y los 5 precios, ajustados) ----

    public function productsTmc(PriceAdjuster $adj): JsonResponse
    {
        return $this->soloTmc() ?? $this->productos(Product::query(), $adj, true);
    }

    public function productByIdTmc(PriceAdjuster $adj, int $id): JsonResponse
    {
        return $this->soloTmc() ?? $this->uno(Product::query()->whereKey($id), $adj, true);
    }

    public function productByNameTmc(PriceAdjuster $adj, string $name): JsonResponse
    {
        return $this->soloTmc() ?? $this->uno(Product::query()->where('name', $name), $adj, true);
    }

    public function productsByBrandTmc(PriceAdjuster $adj, string $brand): JsonResponse
    {
        return $this->soloTmc() ?? $this->productos(Product::query()->where('brand', $brand), $adj, true);
    }

    // ---- Existencias ----

    public function stocks(StockLevelService $levels): JsonResponse
    {
        return $this->existencias($levels, fn (array $s) => true);
    }

    public function stocksByWarehouse(StockLevelService $levels, int $id): JsonResponse
    {
        return $this->existencias($levels, fn (array $s) => $s['id_warehouse'] === $id);
    }

    public function stocksById(StockLevelService $levels, int $id): JsonResponse
    {
        return $this->existencias($levels, fn (array $s) => $s['id_product'] === $id);
    }

    // ---- Internos ----

    private function soloTmc(): ?JsonResponse
    {
        return Auth::user()->esCliente() ? $this->error('No autorizado.', 403) : null;
    }

    private function deBaja(Product $p): bool
    {
        return ProductoBaja::esBaja($p->idERP_empresa ? strtoupper($p->idERP_empresa) : null, $p->name);
    }

    private function productos(Builder $query, PriceAdjuster $adj, bool $tmc): JsonResponse
    {
        if (! Auth::user()->puedeVer('productos')) {
            return $this->error('No autorizado.', 403);
        }

        $filas = $query->get()->reject(fn (Product $p) => $this->deBaja($p))->map(fn (Product $p) => $this->fila($p, $adj, $tmc))->values();

        return $this->ok($filas, 'Productos devueltos exitosamente.');
    }

    /** @param list<string>|null $solo limita los campos devueltos */
    private function uno(Builder $query, PriceAdjuster $adj, bool $tmc, ?array $solo = null): JsonResponse
    {
        if (! Auth::user()->puedeVer('productos')) {
            return $this->error('No autorizado.', 403);
        }

        $p = $query->first();

        if (! $p || $this->deBaja($p)) {
            return $this->error('Product not found.');
        }

        $fila = $this->fila($p, $adj, $tmc);

        return $this->ok($solo ? array_intersect_key($fila, array_flip($solo)) : $fila, 'Product retrieved successfully.');
    }

    private function fila(Product $p, PriceAdjuster $adj, bool $tmc): array
    {
        $precios = $adj->adjustAll([
            'canal_tmc' => $p->price2, 'canal' => $p->price3, 'oem' => $p->price4, 'usuario_final' => $p->price5, 'precio_lista' => $p->list_price,
        ], (string) $p->name, (string) ($p->description_short ?: $p->name2), $p)[0];

        $base = $p->only(self::CAMPOS_EXTERNO);
        $base['list_price'] = $precios['precio_lista'] === null ? null : number_format($precios['precio_lista'], 2, '.', '');

        if (! $tmc) {
            return $base;
        }

        return $base + $p->only(['name2', 'description_short', 'active']) + [
            'price2' => $precios['canal_tmc'], 'price3' => $precios['canal'], 'price4' => $precios['oem'], 'price5' => $precios['usuario_final'],
        ];
    }

    /** Existencias por producto y almacén (ids locales de products/warehouses, como en v1); solo productos y almacenes ya emparejados con el ERP. */
    private function existencias(StockLevelService $levels, Closure $filtro): JsonResponse
    {
        if (! Auth::user()->puedeVer('existencias')) {
            return $this->error('No autorizado.', 403);
        }

        $productos = Product::query()->whereNotNull('idERP')->where('idERP', '!=', '')->whereNotNull('idERP_empresa')->get()
            ->keyBy(fn (Product $p) => strtoupper($p->idERP_empresa).':'.$p->idERP);
        $almacenes = Warehouse::query()->whereNotNull('idERP_empresa')->whereNotNull('almacen_erp_id')->get()
            ->keyBy(fn (Warehouse $w) => strtoupper($w->idERP_empresa).':'.$w->almacen_erp_id);

        $filas = collect($levels->getLevels())->map(function (array $l) use ($productos, $almacenes) {
            $emp = strtoupper($l['empresa_codigo'] ?? 'TRAMEC');
            $p = $productos->get("{$emp}:{$l['id_producto']}");
            $w = $almacenes->get("{$emp}:{$l['id_almacen']}");

            return $p && $w && ! $this->deBaja($p) ? ['id_warehouse' => $w->id, 'id_product' => $p->id, 'quantity' => (float) $l['cantidad']] : null;
        })->filter()->filter($filtro)->values();

        return $this->ok($filas, 'Stocks retrieved successfully.');
    }
}
