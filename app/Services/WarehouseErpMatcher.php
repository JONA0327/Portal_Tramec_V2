<?php

namespace App\Services;

use App\Models\Warehouse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Empareja los almacenes locales (tabla `warehouses`) con el catálogo real del
 * ERP. Muy pocas filas (~9), así que a diferencia de productos esto se corre
 * en vivo en cada visita a /almacenes, no por caché ni scheduler.
 *
 * Usa DOS fuentes de servicio-stock, para cosas distintas:
 *  - GET /api/almacenes/{empresa} (crudo, TODOS los almacenes de esa empresa,
 *    keyeado por su id RAW = almacen_erp_id): es la fuente de verdad para
 *    confirmar que un vínculo YA EXISTENTE sigue siendo válido y para
 *    sincronizar su nombre. Completa siempre, a diferencia de "unificado".
 *  - GET /api/almacenes/unificado (TRAMEC + OMEC con un id_erp único/
 *    deduplicado, pero curado a mano — no incluye TODOS los almacenes, ver
 *    ejemplo real: a OMEC le faltan 3 de sus 4 almacenes ahí): solo se usa
 *    para (a) auto-descubrir por nombre un almacén que JAMÁS tuvo vínculo
 *    (asignándole un id_erp deduplicado) y (b) para la lista de "nuevos" que
 *    ofrece el botón Registrar.
 *
 * Reglas:
 *  - Si un almacén local ya tiene idERP+idERP_empresa+almacen_erp_id y ese
 *    (empresa, almacen_erp_id) sigue existiendo en la lista cruda actual, no
 *    se re-emparienta — pero sí se sincroniza `name` (nunca `alias`, ver
 *    Warehouse::displayName()) si CONTPAQi le cambió el nombre. Esto es lo
 *    que el cliente pidió como "automático": nunca hace falta apretar un
 *    botón para que un nombre editado en CONTPAQi se refleje acá.
 *  - Si ya tenía vínculo pero ese (empresa, almacen_erp_id) YA NO aparece en
 *    la lista cruda actual (se borró/renumeró en CONTPAQi), se deja tal cual
 *    y queda "sin emparejar" -- no se reintenta por nombre contra unificado:
 *    ese vínculo fue puesto a propósito (por register() o a mano) y
 *    reintentar por nombre podría engancharlo por accidente a otro almacén
 *    con nombre corto/parecido de la OTRA empresa (ej. el alias "QRO" de
 *    OMEC enganchándose al "QRO" real de TRAMEC).
 *  - Si nunca tuvo vínculo (idERP null), ahí sí se intenta por nombre
 *    normalizado (trim + mayúsculas) contra "unificado" (necesita su id_erp
 *    deduplicado para poder asignarlo).
 *  - Los almacenes de "unificado" que ningún local reclama son "nuevos": se
 *    listan para que el usuario los registre con un clic (ver
 *    WarehousesController::register()).
 */
class WarehouseErpMatcher
{
    /**
     * @return array{matched_before: int, renamed: int, matched_now: int, unmatched: int, total: int, nuevos: array}
     */
    public function match(): array
    {
        $unificado = $this->fetchUnificado();
        $crudoByKey = $this->fetchCrudoByKey(); // "{empresa}:{almacen_erp_id}" => nombre crudo (completo, TODOS los almacenes)

        // unificado no solo deduplica ids: también "cura" a mano algunos nombres
        // crudos poco claros de CONTPAQi (ej. TRAMEC id=0 es "(Ninguno)" en
        // crudo pero unificado lo muestra como "DELTA Schenker"). Cuando un
        // almacén está cubierto por unificado, se prefiere SU nombre sobre el
        // crudo -- el crudo solo se usa tal cual para lo que unificado no cubre
        // (ej. "Almacen Uno" de OMEC).
        $curatedNameByRawKey = [];
        $byName = [];
        $claimed = [];

        foreach ($unificado as $item) {
            $curatedNameByRawKey["{$item['origen']}:{$item['id']}"] = $item['nombre'];

            $nameKey = $this->normalize($item['nombre'] ?? '');
            if ($nameKey !== '') {
                $byName[$nameKey] ??= $item;
            }
        }

        $stats = ['matched_before' => 0, 'renamed' => 0, 'matched_now' => 0, 'unmatched' => 0, 'total' => 0];

        Warehouse::query()->orderBy('id')->get()->each(function (Warehouse $w) use (&$stats, $crudoByKey, $curatedNameByRawKey, $byName, &$claimed) {
            $stats['total']++;

            if ($w->idERP !== null && $w->idERP_empresa && $w->almacen_erp_id !== null) {
                $rawKey = "{$w->idERP_empresa}:{$w->almacen_erp_id}";

                if (isset($crudoByKey[$rawKey])) {
                    $claimed[$rawKey] = true;
                    $stats['matched_before']++;

                    $nombreActual = $curatedNameByRawKey[$rawKey] ?? $crudoByKey[$rawKey];
                    if ($w->name !== $nombreActual) {
                        $w->forceFill(['name' => $nombreActual])->save();
                        $stats['renamed']++;
                    }

                    return;
                }

                // Vínculo explícito que ya no aparece en la lista cruda: no se
                // reintenta por nombre (ver comentario de la clase).
                $stats['unmatched']++;

                return;
            }

            $match = $byName[$this->normalize($w->name)] ?? null;

            if ($match) {
                $claimed["{$match['origen']}:{$match['id']}"] = true;
                $w->forceFill([
                    'idERP' => $match['id_erp'],
                    'idERP_empresa' => $match['origen'],
                    'almacen_erp_id' => $match['id'],
                ])->save();
                $stats['matched_now']++;

                return;
            }

            $stats['unmatched']++;
        });

        $stats['nuevos'] = collect($unificado)
            ->reject(fn (array $item) => isset($claimed["{$item['origen']}:{$item['id']}"]))
            ->values()
            ->all();

        return $stats;
    }

    /**
     * @return array<string, string> nombre crudo indexado por "empresa:almacen_erp_id" (id RAW, no el id_erp deduplicado)
     */
    private function fetchCrudoByKey(): array
    {
        $result = [];

        foreach (['tramec', 'omec'] as $empresa) {
            $almacenes = Http::withToken(config('services.stock.key'))
                ->baseUrl(config('services.stock.url'))
                ->timeout(20)
                ->get("/api/almacenes/{$empresa}")
                ->throw()
                ->json();

            foreach ($almacenes as $item) {
                $result["{$empresa}:{$item['id']}"] = trim($item['nombre']);
            }
        }

        return $result;
    }

    /**
     * @return array<int, array{id:int,id_erp:int,nombre:string,origen:string,nombre_bd:string}>
     */
    private function fetchUnificado(): array
    {
        return Http::withToken(config('services.stock.key'))
            ->baseUrl(config('services.stock.url'))
            ->timeout(20)
            ->get('/api/almacenes/unificado')
            ->throw()
            ->json();
    }

    private function normalize(?string $value): string
    {
        return Str::of($value ?? '')->trim()->upper()->value();
    }
}
