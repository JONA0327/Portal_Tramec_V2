<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Services\WarehouseErpMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Almacenes: mismo patrón que ProductsController pero mucho más chico (~8
 * filas). El emparejamiento con el ERP (WarehouseErpMatcher) se corre en vivo
 * en cada visita a index() -- no hace falta caché ni scheduler para esta
 * cantidad de datos, y así un nombre editado en CONTPAQi se refleja solo, sin
 * que nadie tenga que apretar nada (pedido explícito del cliente).
 *
 * Tres estados por fila, igual que en productos:
 *  - normal:      almacén local emparejado con el ERP (nombre al día).
 *  - nuevo:       existe en el ERP pero todavía no hay almacén local para él.
 *  - advertencia: hay almacén local pero no se encontró en el catálogo actual
 *                 del ERP (ej. "MDG", deprecado, confirmado con el cliente).
 */
class WarehousesController extends Controller
{
    public function index(WarehouseErpMatcher $matcher): View
    {
        try {
            $stats = $matcher->match();
        } catch (\Illuminate\Http\Client\ConnectionException|\Illuminate\Http\Client\RequestException $e) {
            \App\Support\StockHealth::down();
            // Sin ERP: se muestran los almacenes locales tal cual, sin emparejar ni detectar nuevos.
            $total = Warehouse::count();
            $matched = Warehouse::query()->get()->filter->isMatched()->count();
            $stats = ['matched_before' => $matched, 'renamed' => 0, 'matched_now' => 0, 'unmatched' => $total - $matched, 'total' => $total, 'nuevos' => []];
        }

        $rows = Warehouse::query()->orderBy('id')->get()->map(fn (Warehouse $w) => [
            'local_id' => $w->id,
            'nombre' => $w->name,
            'id_erp' => $w->idERP,
            'empresa' => $w->idERP_empresa,
            'estado' => $w->isMatched() ? 'normal' : 'advertencia',
        ]);

        $nuevos = collect($stats['nuevos'])->map(fn (array $item) => [
            'local_id' => null,
            'nombre' => $item['nombre'],
            'id_erp' => $item['id_erp'],
            'empresa' => $item['origen'],
            'almacen_erp_id' => $item['id'],
            'estado' => 'nuevo',
        ]);

        return view('almacenes', [
            'warehouses' => $rows->concat($nuevos)->values(),
            'stats' => $stats,
            'lastSyncAt' => app(\App\Services\StockService::class)->lastSyncAt(),
        ]);
    }

    /**
     * Registra un almacén "nuevo" (existe en el ERP, todavía no tiene fila
     * local) con un solo clic: crea el registro ya vinculado al id_erp, con el
     * nombre que trae el ERP en ese momento (ver App\Services\WarehouseErpMatcher
     * para cómo se sincroniza después si CONTPAQi le cambia el nombre).
     */
    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'empresa' => ['required', 'string'],
            'id_erp' => ['required', 'integer'],
            'almacen_erp_id' => ['required', 'integer'],
            'nombre' => ['required', 'string'],
        ]);

        // idERP/idERP_empresa/almacen_erp_id no están en $fillable a propósito
        // (ver Warehouse::$fillable), así que se ponen con forceFill().
        $warehouse = new Warehouse();
        $warehouse->forceFill([
            'name' => $data['nombre'],
            'idERP' => $data['id_erp'],
            'idERP_empresa' => strtolower($data['empresa']),
            'almacen_erp_id' => $data['almacen_erp_id'],
        ]);
        $warehouse->save();

        return back()->with('status', "Almacén \"{$data['nombre']}\" registrado y emparejado con el ERP.");
    }

    public function confirmDestroy(Warehouse $warehouse): View
    {
        return view('almacenes.confirm-destroy', ['warehouse' => $warehouse]);
    }

    public function destroy(Request $request, Warehouse $warehouse): RedirectResponse
    {
        // Misma seguridad extra que en productos: hay que escribir "confirmar".
        $request->validate(['confirmacion' => ['required', 'string']]);

        if (strtolower(trim($request->input('confirmacion'))) !== 'confirmar') {
            return back()
                ->withErrors(['confirmacion' => 'Tenés que escribir la palabra "confirmar" para borrar el almacén.'])
                ->withInput();
        }

        $warehouse->delete();

        return redirect()->route('warehouses')->with('status', 'Almacén eliminado.');
    }
}
