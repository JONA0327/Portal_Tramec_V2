<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Bitácora de accesos: igual que v1 (LoginController@authenticated), cada login
        // se registra en la tabla "sessions" (que aquí NO es el session store de Laravel,
        // es una tabla de negocio propia que se lista en /admin/sessions). Se lee más
        // adelante desde StockController@sessions en v1.
        DB::table('sessions')->insert([
            'user_id'    => $request->user()->id,
            'email'      => $request->user()->email,
            'loggedin'   => now(),
            'ip'         => $request->ip(),
            'device'     => $request->userAgent(),
        ]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
