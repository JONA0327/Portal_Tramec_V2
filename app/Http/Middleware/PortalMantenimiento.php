<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modo mantenimiento por variable de entorno: con PORTAL_MANTENIMIENTO=true nadie entra al portal y todos ven la
 * pantalla de mantenimiento (503). Siguen funcionando /up (chequeo de salud) y el webhook de existencias, que no son
 * personas entrando al portal. Se apaga con PORTAL_MANTENIMIENTO=false (y php artisan config:clear si la configuración está en caché).
 */
class PortalMantenimiento
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.mantenimiento') && ! $request->is('up', 'webhooks/*')) {
            return response()->view('errors.mantenimiento', [], 503)->header('Retry-After', '3600');
        }

        return $next($request);
    }
}
