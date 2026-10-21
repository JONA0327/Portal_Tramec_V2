<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las páginas de acceso (login, registro...) llevan un token CSRF atado a la sesión: si el
 * navegador o Cloudflare guardan el HTML y lo reutilizan, el formulario se envía con un
 * token viejo y da 419. Con no-store siempre se pide la página (y su token) nuevos.
 */
class NoStore
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
