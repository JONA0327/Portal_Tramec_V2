<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se dispara cuando StockLevelService detecta que cambió la cantidad de un
 * producto en un almacén (sync completo o webhook, ver
 * servicio-stock::_vigilar_existencias). Canal público, mismo criterio que
 * ProductChanged: la existencia ya es visible para cualquier usuario
 * autenticado del portal.
 */
class ExistenciaChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public array $level)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('existencias-updates');
    }

    public function broadcastAs(): string
    {
        return 'existencia.changed';
    }

    public function broadcastWith(): array
    {
        return $this->level;
    }
}
