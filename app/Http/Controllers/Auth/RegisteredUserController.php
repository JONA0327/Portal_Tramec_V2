<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\UserInfo;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // La tabla real de usuarios (heredada de v1) guarda first_name/last_name en vez
        // de un solo campo "name", y el formulario de v1 no pide confirmar el password.
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', Rules\Password::defaults()],
        ]);

        // active_flag => 0: igual que v1, el usuario nuevo queda inactivo hasta que
        // alguien de Tramec lo revise a partir del correo de aviso de abajo.
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'active_flag' => 0,
        ]);

        // Aviso interno a Tramec de que hay un registro nuevo (mismo destinatario que v1).
        Mail::to('info@tramec.mx')->send(new UserInfo($user));

        event(new Registered($user));

        // Sin login automático: la cuenta queda pendiente hasta que un administrador la active.
        return redirect()->route('login')->with('status', 'Registro recibido. Tu cuenta quedará activa cuando un administrador de Tramec la apruebe.');
    }
}
