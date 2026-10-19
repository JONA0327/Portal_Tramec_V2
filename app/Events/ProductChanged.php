<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara cuando StockService detecta (vía /api/cambios) que un producto cambió.
 * El canal es público: el catálogo ya es visible para cualquier usuario autenticado
 * del portal, así que no hace falta routes/channels.php ni auth de broadcasting.
 */
class ProductChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public array $product)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('stock-updates');
    }

    public function broadcastAs(): string
    {
        return 'product.changed';
    }

    public function broadcastWith(): array
    {
        return $this->product;
    }
}
