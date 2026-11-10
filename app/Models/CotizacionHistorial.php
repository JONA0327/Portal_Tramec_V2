<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cotización guardada (una por moneda) al descargar el PDF con "Guardar en el historial". */
class CotizacionHistorial extends Model
{
    protected $table = 'cotizaciones_historial';

    protected $fillable = ['user_id', 'folio', 'moneda', 'fecha', 'cliente_empresa', 'emisor', 'total', 'datos'];

    protected function casts(): array
    {
        return ['datos' => 'array', 'fecha' => 'date', 'total' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Foto de lo que se va a descargar: datos generales + renglones de UNA moneda. */
    public static function desde(Cotizacion $c, string $moneda, array $grupo, string $folio, int $userId): self
    {
        return static::create([
            'user_id' => $userId,
            'folio' => $folio,
            'moneda' => $moneda,
            'fecha' => $c->fecha,
            'cliente_empresa' => $c->cliente_empresa,
            'emisor' => $c->emisor,
            'total' => $grupo['total'],
            'datos' => [
                'cotizacion' => $c->only(['vigencia', 'cliente_empresa', 'cliente_atencion', 'cliente_telefono', 'condiciones_pago', 'terminos',
                    'emisor', 'emisor_email', 'emisor_telefono', 'precio_base', 'descuento_pct', 'iva_pct']) + ['fecha' => $c->fecha?->toDateString()],
                'items' => $grupo['items']->map(fn (CotizacionItem $i) => $i->only(['empresa', 'codigo', 'descripcion', 'moneda', 'precios',
                    'precio_manual', 'cantidad', 'unidad', 'tiempo_entrega', 'canal']))->values()->all(),
            ],
        ]);
    }

    /** Reconstruye (sin guardar) la cotización y sus renglones para volver a generar el PDF idéntico. */
    public function reconstruir(): Cotizacion
    {
        $c = (new Cotizacion)->forceFill($this->datos['cotizacion']);
        $c->setRelation('items', collect($this->datos['items'])->map(fn (array $i) => (new CotizacionItem)->forceFill($i)));

        return $c;
    }
}
