<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Acceso por módulo según users.level (mismas reglas que el menú de v1, ver
 * User::puedeVer()). Uso: ->middleware('nivel:existencias').
 */
class EnsureLevelCanAccess
{
    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        abort_unless($request->user()?->puedeVer($modulo), 403, 'Tu nivel de usuario no tiene acceso a esta sección.');

        return $next($request);
    }
}
