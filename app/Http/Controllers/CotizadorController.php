<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\CotizacionHistorial;
use App\Models\CotizacionItem;
use App\Models\ExistenciaOverride;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\PriceAdjuster;
use App\Services\StockService;
use App\Support\Codigo;
use Dompdf\Dompdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Carrito y cotizador (administradores y equipo Tramec, ver User::puedeCotizar()). Los renglones se agregan desde
 * Productos y Existencias; aquí se editan los datos generales, los renglones y se descarga el PDF. Una cotización no
 * mezcla monedas: cada moneda sale como su propio PDF con sus totales.
 */
class CotizadorController extends Controller
{
    public function index(Request $request): View
    {
        $cotizacion = Cotizacion::deUsuario($request->user())->load('items');

        return view('cotizador', [
            'cotizacion' => $cotizacion,
            'grupos' => $cotizacion->gruposPorMoneda(),
            'columnas' => $request->user()->columnasPrecio(),
        ]);
    }

    /**
     * Agrega al carrito lo marcado en Productos / Existencias. Cada item es "erp:EMPRESA:ID" (catálogo del ERP),
     * "loc:ID" (producto local sin emparejar) o "ex:EMPRESA:ID_PRODUCTO:ID_ALMACEN" (fila de Existencias).
     */
    public function agregar(Request $request, StockService $stock, PriceAdjuster $adjuster): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*' => ['string', 'max:200'],
        ]);

        $cotizacion = Cotizacion::deUsuario($request->user());
        $columnas = array_keys($request->user()->columnasPrecio());
        $catalogo = collect($stock->enCache()); // solo la caché: con el servicio caído el carrito no debe esperar ni fallar
        $agregados = $repetidos = $omitidos = 0;

        foreach (array_unique($data['items']) as $key) {
            $info = $this->resolver($key, $catalogo, $adjuster, $columnas);

            if (! $info) {
                $omitidos++;

                continue;
            }

            $yaEsta = $cotizacion->items()->where('codigo', $info['codigo'])->where('empresa', $info['empresa'])->exists();

            if ($yaEsta) {
                $repetidos++;

                continue;
            }

            $cotizacion->items()->create($info + ['cantidad' => 1, 'unidad' => 'PZA.']);
            $agregados++;
        }

        $msg = "{$agregados} producto(s) agregado(s) al carrito de cotización";
        $msg .= $repetidos ? ", {$repetidos} ya estaban" : '';
        $msg .= $omitidos ? ", {$omitidos} no se pudieron agregar" : '';

        return back()->with('status', $msg.'. Ábrelo en Cotizador.');
    }

    public function guardar(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->guardarCambios($request);

        if ($request->ajax()) {
            return response()->json(['ok' => true]); // guardado automático desde la página
        }

        return redirect()->route('cotizador')->with('status', 'Cotización guardada.');
    }

    /** Guarda lo capturado y devuelve el PDF de UNA moneda. */
    public function pdf(Request $request, string $moneda): Response|RedirectResponse
    {
        $cotizacion = $this->guardarCambios($request);
        $moneda = strtoupper($moneda);
        $grupos = $cotizacion->gruposPorMoneda();

        if (! isset($grupos[$moneda])) {
            return redirect()->route('cotizador')->withErrors(['moneda' => "No hay productos en {$moneda}."]);
        }

        if ($cotizacion->folio_numero === null) {
            $cotizacion->update(['folio_numero' => Cotizacion::siguienteFolio($cotizacion->serie)]);
        }

        $folio = $cotizacion->folioCompleto();
        // Con más de una moneda cada PDF es una cotización distinta: el folio lleva el sufijo de la moneda.
        $folioPdf = count($grupos) > 1 ? ($folio !== '' ? "{$folio}-{$moneda}" : $moneda) : $folio;

        $pdf = $this->renderPdf($cotizacion, $moneda, $grupos[$moneda], $folioPdf);

        // "Guardar en el historial": foto de esta cotización (una por moneda) para volver a descargarla después.
        if ($request->boolean('guardar_historial')) {
            CotizacionHistorial::desde($cotizacion, $moneda, $grupos[$moneda], $folioPdf, $request->user()->id);
        }

        // Ya cotizado: se vacía del carrito lo de esta moneda; sin más renglones, la cotización queda nueva (folio nuevo).
        $cotizacion->items()->whereIn('id', $grupos[$moneda]['items']->pluck('id'))->delete();
        if (! $cotizacion->items()->exists()) {
            $cotizacion->delete();
        }

        return $this->descarga($pdf, $folioPdf);
    }

    public function historial(Request $request): View
    {
        $q = CotizacionHistorial::with('user')->latest('id');

        if (! $request->user()->isAdmin()) {
            $q->where('user_id', $request->user()->id); // cada quien ve las suyas; el administrador, todas
        }

        // Filtros: folio, cliente y fecha (nada más).
        $folio = trim((string) $request->query('folio', ''));
        $cliente = trim((string) $request->query('cliente', ''));
        $fecha = trim((string) $request->query('fecha', ''));

        $escape = fn (string $t) => addcslashes($t, '%_\\');
        $folio !== '' && $q->where('folio', 'like', '%'.$escape($folio).'%');
        $cliente !== '' && $q->where('cliente_empresa', 'like', '%'.$escape($cliente).'%');
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) && $q->whereDate('fecha', $fecha);

        $porPagina = \App\Support\PorPagina::desde($request, 20); // 10 / 25 / 50 / 100, como en Productos

        return view('cotizador-historial', [
            'registros' => $q->paginate($porPagina)->withQueryString(),
            'porPagina' => $porPagina,
            'verUsuario' => $request->user()->isAdmin(),
            'filtros' => ['folio' => $folio, 'cliente' => $cliente, 'fecha' => $fecha],
            'carritoItems' => Cotizacion::where('user_id', $request->user()->id)->withCount('items')->first()?->items_count ?? 0,
        ]);
    }

    /** "Editar": vacía el carrito y lo vuelve a llenar con lo de esa cotización para generarla de nuevo (con las modificaciones). */
    public function historialEditar(Request $request, CotizacionHistorial $registro): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $registro->user_id === $request->user()->id, 404);

        $d = $registro->datos;
        $campos = ['vigencia', 'cliente_empresa', 'cliente_atencion', 'cliente_telefono', 'condiciones_pago', 'terminos',
            'emisor', 'emisor_email', 'emisor_telefono', 'precio_base', 'descuento_pct', 'iva_pct'];

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $registro, $d, $campos) {
            Cotizacion::where('user_id', $request->user()->id)->delete(); // los renglones se van en cascada

            $c = Cotizacion::create(collect($d['cotizacion'])->only($campos)->map(fn ($v) => $v ?? '')->all() + [
                'user_id' => $request->user()->id,
                'fecha' => now()->toDateString(),
                'serie' => $registro->serie,
                'folio_numero' => $registro->folio_numero,
                'folio' => Cotizacion::armarFolio($registro->serie, $registro->folio_numero),
            ]);

            foreach ($d['items'] as $item) {
                $c->items()->create(collect($item)->only(['empresa', 'codigo', 'descripcion', 'moneda', 'precios', 'precio_manual', 'cantidad', 'unidad', 'tiempo_entrega', 'canal'])->all());
            }
        });

        return redirect()->route('cotizador')->with('status', 'Cotización cargada en el carrito para editarla y generarla de nuevo.');
    }

    /** Folio sugerido al cambiar la serie en pantalla. */
    public function siguienteFolio(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json(['siguiente' => Cotizacion::siguienteFolio((string) $request->query('serie', ''))]);
    }

    /** Renglón libre: código y descripción de texto libre (otros productos o servicios) con su precio y moneda. */
    public function libre(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'libre_codigo' => ['required', 'string', 'max:100'],
            'libre_descripcion' => ['nullable', 'string', 'max:500'],
            'libre_moneda' => ['required', Rule::in(['MXN', 'USD'])],
            'libre_precio' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'libre_tiempo_entrega' => ['nullable', 'string', 'max:100'],
        ]);

        Cotizacion::deUsuario($request->user())->items()->create([
            'empresa' => null,
            'codigo' => trim($data['libre_codigo']),
            'descripcion' => $data['libre_descripcion'] ?? null,
            'moneda' => $data['libre_moneda'],
            'precios' => [],
            'precio_manual' => (float) ($data['libre_precio'] ?? 0),
            'cantidad' => 1,
            'unidad' => 'PZA.',
            'tiempo_entrega' => $data['libre_tiempo_entrega'] ?? null,
        ]);

        return back()->with('status', 'Renglón libre agregado.');
    }

    public function historialPdf(Request $request, CotizacionHistorial $registro): Response
    {
        abort_unless($request->user()->isAdmin() || $registro->user_id === $request->user()->id, 404);

        $c = $registro->reconstruir();

        return $this->descarga($this->renderPdf($c, $registro->moneda, $c->gruposPorMoneda()[$registro->moneda], (string) $registro->folio), (string) $registro->folio);
    }

    public function historialEliminar(Request $request, CotizacionHistorial $registro): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $registro->user_id === $request->user()->id, 404);
        $registro->delete();

        return back()->with('status', 'Cotización eliminada del historial.');
    }

    private function renderPdf(Cotizacion $cotizacion, string $moneda, array $grupo, string $folio): string
    {
        $html = view('cotizador-pdf', [
            'c' => $cotizacion,
            'moneda' => $moneda,
            'g' => $grupo,
            'folio' => $folio,
            'logo' => $this->logoBase64(),
            'qr' => $this->imagenBase64('qr-tramec.png'), // QR simple a https://tramec.mx/
            'marcas' => collect(['delta', 'omec', 'stm', 'wolong', 'oli-sick'])->mapWithKeys(fn ($m) => [$m => $this->imagenBase64("pdf/marca-{$m}.png")])->filter()->all(), // pie del PDF
        ])->render();

        $dompdf = new Dompdf(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('letter');
        $dompdf->render();

        return $dompdf->output();
    }

    private function descarga(string $pdf, string $folio): Response
    {
        $archivo = 'Cotizacion '.preg_replace('/[^A-Za-z0-9._-]+/', '_', $folio !== '' ? $folio : 'sin-folio').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$archivo.'"',
        ]);
    }

    public function quitar(Request $request, CotizacionItem $item): RedirectResponse
    {
        abort_unless($item->cotizacion_id === Cotizacion::deUsuario($request->user())->id, 404);
        $item->delete();

        return back()->with('status', "{$item->codigo} quitado del carrito.");
    }

    /** Vacía el carrito y deja los datos generales como nuevos (fecha de hoy, términos de siempre). */
    public function vaciar(Request $request): RedirectResponse
    {
        Cotizacion::where('user_id', $request->user()->id)->delete(); // los renglones se van en cascada

        return redirect()->route('cotizador')->with('status', 'Carrito vaciado. Cotización nueva.');
    }

    // ------------------------------------------------------------------------------------------------

    private function guardarCambios(Request $request): Cotizacion
    {
        $cotizacion = Cotizacion::deUsuario($request->user());
        $columnas = array_keys($request->user()->columnasPrecio());

        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'serie' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9._-]*$/'],
            'folio_numero' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'vigencia' => ['nullable', 'string', 'max:60'],
            'cliente_empresa' => ['nullable', 'string', 'max:191'],
            'cliente_atencion' => ['nullable', 'string', 'max:191'],
            'cliente_telefono' => ['nullable', 'string', 'max:60'],
            'condiciones_pago' => ['nullable', 'string', 'max:191'],
            'terminos' => ['nullable', 'string', 'max:3000'],
            'emisor' => ['nullable', 'string', 'max:191'],
            'emisor_email' => ['nullable', 'string', 'max:191'],
            'emisor_telefono' => ['nullable', 'string', 'max:60'],
            'precio_base' => ['required', Rule::in($columnas)],
            'descuento_pct' => ['nullable', 'numeric', 'between:0,100'],
            'iva_pct' => ['nullable', 'numeric', 'between:0,100'],
            'items' => ['nullable', 'array'],
            'items.*.descripcion' => ['nullable', 'string', 'max:500'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'items.*.precio' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'items.*.unidad' => ['nullable', 'string', 'max:20'],
            'items.*.tiempo_entrega' => ['nullable', 'string', 'max:100'],
            'items.*.canal' => ['nullable', Rule::in($columnas)],
        ]);

        $serie = mb_strtoupper(trim((string) ($data['serie'] ?? '')));
        $numero = ($data['folio_numero'] ?? '') === '' ? null : (int) $data['folio_numero'];

        // Cada usuario conserva la última serie que usó para sus próximas cotizaciones.
        if ((string) $request->user()->cotizacion_serie !== $serie) {
            \Illuminate\Support\Facades\DB::table('users')->where('id', $request->user()->id)->update(['cotizacion_serie' => $serie ?: null]);
        }

        $cotizacion->update(collect($data)->except(['items', 'serie', 'folio_numero'])->map(fn ($v) => $v ?? '')->all() + [
            'serie' => $serie ?: null,
            'folio_numero' => $numero,
            'folio' => Cotizacion::armarFolio($serie, $numero),
            'descuento_pct' => $data['descuento_pct'] ?? 0,
            'iva_pct' => $data['iva_pct'] ?? 0,
        ]);

        foreach ($cotizacion->items()->get() as $item) {
            $f = $data['items'][$item->id] ?? null;

            if (! $f) {
                continue;
            }

            // Si el precio capturado es igual al de la columna elegida no hay precio manual (así sigue a la columna).
            $canal = ($f['canal'] ?? '') ?: $cotizacion->precio_base;
            $base = (float) ($item->precios[$canal] ?? 0);
            $precio = ($f['precio'] ?? '') === '' || $f['precio'] === null ? null : (float) $f['precio'];
            $manual = ($precio === null || abs($precio - $base) < 0.005) ? null : $precio;

            $item->update([
                'descripcion' => $f['descripcion'] ?? $item->descripcion,
                'cantidad' => $f['cantidad'],
                'unidad' => ($f['unidad'] ?? $item->unidad) ?: 'PZA.', // ya no se captura en el carrito: se conserva (por defecto PZA.)
                'tiempo_entrega' => $f['tiempo_entrega'] ?? null,
                'precio_manual' => $manual,
                'canal' => $canal,
            ]);
        }

        return $cotizacion->refresh()->load('items');
    }

    /**
     * Convierte un item marcado en los datos de un renglón. Los precios ya llevan las reglas de descuento/aumento
     * que ve este usuario (lo que no puede ver, no lo puede cotizar).
     *
     * @return array<string, mixed>|null
     */
    private function resolver(string $key, $catalogo, PriceAdjuster $adjuster, array $columnas): ?array
    {
        $erp = null;
        $local = null;
        $almacen = null;
        $override = null;

        if (preg_match('/^erp:([A-Za-z]+):(\d+)$/', $key, $m)) {
            $empresa = strtoupper($m[1]);
            $erp = $this->enCatalogo($catalogo, $empresa, (int) $m[2]);
        } elseif (preg_match('/^ex:([A-Za-z]+):(\d+):(\d+)$/', $key, $m)) {
            $empresa = strtoupper($m[1]);
            $erp = $this->enCatalogo($catalogo, $empresa, (int) $m[2]);
            $override = ExistenciaOverride::where(['empresa' => $empresa, 'id_producto' => (int) $m[2], 'id_almacen' => (int) $m[3]])->first();
            $almacen = Warehouse::whereRaw('LOWER(idERP_empresa) = ?', [strtolower($empresa)])->where('almacen_erp_id', (int) $m[3])->first();
        } elseif (preg_match('/^loc:(\d+)$/', $key, $m)) {
            $local = Product::find($m[1]);
            $empresa = $local?->idERP_empresa ? strtoupper($local->idERP_empresa) : null;
        } else {
            return null;
        }

        // Sin catálogo (servicio de existencias caído y caché sin ese producto): se usa la ficha local, que guarda el vínculo
        // con el ERP (idERP + empresa), para que el carrito siga funcionando.
        if (! $local && ! $erp && isset($m[2], $empresa)) {
            $local = Product::where('idERP', (int) $m[2])->whereRaw('UPPER(idERP_empresa) = ?', [$empresa])->first();
        }

        $codigo = $erp['codigo'] ?? $local?->name ?? $override?->codigo;

        if (! $codigo) {
            return null;
        }

        if (! $local && $erp) {
            $local = Product::where('idERP', $erp['id'])->where('idERP_empresa', $empresa)->first();
        }

        // Existencias de Schenker que solo vienen del Excel: se busca la ficha local por código sin espacios.
        $local ??= Product::get()->first(fn (Product $p) => Codigo::normalizar($p->name) === Codigo::normalizar($codigo));

        $descripcion = $local?->description_short ?: ($erp['nombre'] ?? $override?->nombre ?? $local?->name2);
        $base = [
            'canal_tmc' => $local?->price2,
            'canal' => $local?->price3,
            'oem' => $local?->price4,
            'usuario_final' => $local?->price5,
            'precio_lista' => $local?->list_price,
        ];
        [$ajustados, , $temporales] = $adjuster->adjustAll(array_intersect_key($base, array_flip($columnas)), $codigo, (string) $descripcion, $local);
        // Precio temporal: se cotiza el temporal (quien lo ve en verde junto al original también cotiza el verde).
        $ajustados = $temporales + $ajustados;

        return [
            'empresa' => $empresa,
            'codigo' => $codigo,
            'descripcion' => $descripcion === 'Sin descripción' ? null : $descripcion,
            'moneda' => strtoupper($local?->base_currency ?: 'MXN'),
            'precios' => $ajustados,
            'tiempo_entrega' => $almacen ? 'STOCK '.($almacen->alias ?: $almacen->name) : null,
        ];
    }

    private function enCatalogo($catalogo, string $empresa, int $id): ?array
    {
        return $catalogo->first(fn (array $p) => strtoupper($p['empresa_codigo'] ?? 'TRAMEC') === $empresa && (int) ($p['id'] ?? 0) === $id);
    }

    private function imagenBase64(string $archivo): ?string
    {
        $ruta = public_path($archivo);

        return is_file($ruta) ? 'data:image/'.(str_ends_with($archivo, '.jpg') ? 'jpeg' : 'png').';base64,'.base64_encode(file_get_contents($ruta)) : null;
    }

    private function logoBase64(): ?string
    {
        $ruta = public_path('Logo-Tramec-MX-removebg-preview.png');

        return is_file($ruta) ? 'data:image/png;base64,'.base64_encode(file_get_contents($ruta)) : null;
    }
}
