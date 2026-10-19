<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un usuario con active_flag distinto de 1 (los registros nuevos nacen en 0)
 * no puede usar el portal hasta que un administrador lo active. Corre en todo
 * el grupo web, así que también saca a quien se desactive con la sesión abierta.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (int) $user->active_flag !== 1 && ! $request->routeIs('logout')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta está inactiva o pendiente de aprobación. Un administrador de Tramec debe activarla.',
            ]);
        }

        return $next($request);
    }
}
