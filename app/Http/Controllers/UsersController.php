<?php

namespace App\Http\Controllers;

use App\Models\PriceRule;
use App\Models\User;
use App\Support\PorPagina;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Administración de usuarios (equivalente a admin/users de v1): alta, edición,
 * activar/desactivar y asignar nivel (users.level, ver PriceRule::NIVELES).
 * Solo ADMINISTRADOR -- ver el middleware en routes/web.php. No hay borrado:
 * un usuario que ya no debe entrar se desactiva.
 */
class UsersController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $estado = (string) $request->query('estado', '');
        $nivel = (string) $request->query('nivel', '');

        $users = User::query()
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('first_name', 'like', "%{$buscar}%")
                ->orWhere('last_name', 'like', "%{$buscar}%")
                ->orWhere('email', 'like', "%{$buscar}%")))
            ->when($estado === 'activos', fn ($q) => $q->where('active_flag', 1))
            ->when($estado === 'inactivos', fn ($q) => $q->where(fn ($q) => $q->where('active_flag', '!=', 1)->orWhereNull('active_flag')))
            ->when($nivel === 'sin', fn ($q) => $q->whereNull('level'))
            ->when(ctype_digit($nivel), fn ($q) => $q->where('level', (int) $nivel))
            // Los pendientes de aprobación primero, luego por nombre.
            ->orderByRaw('COALESCE(active_flag, 0) asc')
            ->orderBy('first_name')
            ->paginate(PorPagina::desde($request, self::PER_PAGE))
            ->withQueryString();

        return view('usuarios.index', [
            'users' => $users,
            'perPage' => $users->perPage(),
            'buscar' => $buscar,
            'estado' => $estado,
            'nivel' => $nivel,
            'pendientes' => User::where(fn ($q) => $q->where('active_flag', '!=', 1)->orWhereNull('active_flag'))->count(),
        ]);
    }

    public function create(): View
    {
        return view('usuarios.form', ['user' => new User(['active_flag' => 1])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules() + [
            'password' => ['required', Password::defaults()],
        ]);

        User::create($data + ['active_flag' => $request->boolean('active_flag') ? 1 : 0]);

        return redirect()->route('users.index')->with('status', 'Usuario creado.');
    }

    public function edit(User $user): View
    {
        return view('usuarios.form', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate($this->rules($user) + [
            'password' => ['nullable', Password::defaults()],
        ]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']); // vacío = no cambiar la contraseña
        }

        $activo = $request->boolean('active_flag');

        if ($user->is($request->user()) && (! $activo || (int) $data['level'] !== 0)) {
            return back()->withInput()->withErrors(['level' => 'No podés quitarte tu propio acceso de administrador ni desactivarte.']);
        }

        $user->update($data + ['active_flag' => $activo ? 1 : 0]);

        return redirect()->route('users.index')->with('status', 'Usuario actualizado.');
    }

    /** Cambio rápido desde el listado: nivel y/o estado. */
    public function quick(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'level' => ['sometimes', 'nullable', Rule::in(array_keys(PriceRule::NIVELES))],
            'active_flag' => ['sometimes', 'boolean'],
        ]);

        if ($user->is($request->user())
            && ((array_key_exists('active_flag', $data) && ! $data['active_flag'])
                || (array_key_exists('level', $data) && (int) $data['level'] !== 0))) {
            return back()->with('error', 'No podés desactivarte ni quitarte el nivel de administrador a vos mismo.');
        }

        $user->update($data);

        return back()->with('status', "{$user->first_name} {$user->last_name}: actualizado.");
    }

    private function rules(?User $user = null): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'level' => ['required', Rule::in(array_keys(PriceRule::NIVELES))],
        ];
    }
}
