<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cotización en borrador de un usuario (una por usuario). Ver la migración cotizaciones.
 */
class Cotizacion extends Model
{
    protected $table = 'cotizaciones';

    public const CONDICIONES_PAGO = ['CONTADO', '30 DIAS', '15 DIAS', '50% ANTICIPO 50% CONTRA ENTREGA', '50% ANTICIPO 50% AVISO DE EMBARQUE', '50% ANTICIPO 50% A CREDITO'];

    public const TERMINOS_PREDETERMINADOS = "* Los precios son expresados en el tipo de moneda indicado.\n* Tiempo de entrega sobre previa venta.\n* Precios sujetos a cambio sin previo aviso debido a la fluctuación del mercado.";

    protected $fillable = [
        'user_id', 'fecha', 'serie', 'folio_numero', 'folio', 'vigencia', 'cliente_empresa', 'cliente_atencion', 'cliente_telefono', 'condiciones_pago',
        'terminos', 'emisor', 'emisor_email', 'emisor_telefono', 'precio_base', 'descuento_pct', 'iva_pct',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'descuento_pct' => 'float', 'iva_pct' => 'float'];
    }

    /**
     * Folio sugerido para una serie: el siguiente número consecutivo de las cotizaciones GUARDADAS en el historial con esa
     * serie (empieza en 1). El portal permite folios repetidos: el usuario puede escribir cualquier número.
     */
    public static function siguienteFolio(?string $serie): int
    {
        $serie = trim((string) $serie);

        $max = CotizacionHistorial::query()
            ->where(fn ($q) => $serie === '' ? $q->whereNull('serie')->orWhere('serie', '') : $q->where('serie', $serie))
            ->max('folio_numero');

        return ((int) $max) + 1;
    }

    /** Serie + número de 4 dígitos (JL0001); vacío si no hay número. */
    public static function armarFolio(?string $serie, $numero): string
    {
        return $numero === null || $numero === '' ? '' : trim((string) $serie).str_pad((string) (int) $numero, 4, '0', STR_PAD_LEFT);
    }

    public function folioCompleto(): string
    {
        return self::armarFolio($this->serie, $this->folio_numero);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CotizacionItem::class)->orderBy('id');
    }

    /** El borrador del usuario; si no tiene, se crea con valores iniciales (fecha de hoy, emisor, términos de siempre). */
    public static function deUsuario(User $user): self
    {
        return static::firstOrCreate(['user_id' => $user->id], [
            'fecha' => now()->toDateString(),
            'serie' => $user->cotizacion_serie,
            'folio_numero' => self::siguienteFolio($user->cotizacion_serie),
            'vigencia' => '30 Días.',
            'condiciones_pago' => 'CONTADO',
            'terminos' => self::TERMINOS_PREDETERMINADOS,
            'emisor' => trim($user->first_name.' '.$user->last_name),
            'emisor_email' => $user->cotizacion_email ?? $user->email,
            'emisor_telefono' => $user->cotizacion_telefono,
            'precio_base' => 'precio_lista',
            'descuento_pct' => 0,
            'iva_pct' => 16,
        ]);
    }

    /**
     * Renglones separados por moneda: una cotización no mezcla monedas, así que cada moneda se cotiza (y se
     * descarga) por separado, con sus propios totales.
     *
     * @return array<string, array{items: \Illuminate\Support\Collection, subtotal: float, descuento: float, iva: float, total: float}>
     */
    public function gruposPorMoneda(): array
    {
        $grupos = [];

        foreach ($this->items->groupBy('moneda') as $moneda => $items) {
            $subtotal = round($items->sum(fn (CotizacionItem $i) => $i->importe($this->precio_base)), 2);
            $descuento = round($subtotal * $this->descuento_pct / 100, 2);
            $base = $subtotal - $descuento;
            $iva = round($base * $this->iva_pct / 100, 2);

            $grupos[$moneda] = [
                'items' => $items->values(),
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'iva' => $iva,
                'total' => round($base + $iva, 2),
            ];
        }

        ksort($grupos);

        return $grupos;
    }
}
