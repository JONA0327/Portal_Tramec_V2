<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** API para terceros: "Authorization: Bearer <token>" (ver ApiController@login o `php artisan api:token`). */
class AutenticarApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        $fila = $token === '' ? null : DB::table('api_tokens')->where('token', hash('sha256', $token))->first();
        $user = $fila ? User::find($fila->user_id) : null;

        if (! $user || (int) $user->active_flag !== 1) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 401);
        }

        Auth::setUser($user);
        DB::table('api_tokens')->where('id', $fila->id)->update(['last_used_at' => now()]);

        return $next($request);
    }
}
