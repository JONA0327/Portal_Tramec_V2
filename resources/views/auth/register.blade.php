{{-- Registro de v2, mismo diseño que v1 (portal-tramec/resources/views/auth/register.blade.php),
     armado con los mismos componentes que ya usa login.blade.php.
     Ojo: v1 pide "Nombre"/"Apellido" (first_name/last_name), no un solo campo "name" como
     trae Breeze por defecto -> RegisteredUserController se ajustó para guardar así. --}}
<x-layouts.auth>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <a href="/" class="ad-auth-logo">
            <h4 class="text-center">PORTAL DE EXISTENCIAS TRAMEC</h4>
        </a>

        <h2><span class="primary">Hola,</span>¡Bienvenido!</h2>
        <p>Introduzca los datos solicitados a continuación:</p>

        <x-auth.errors />

        <div class="ad-auth-form">
            <x-auth.field type="text" name="first_name" placeholder="Nombre" :value="old('first_name')" class="mb-30">
                <x-icons.person-icon />
            </x-auth.field>

            <x-auth.field type="text" name="last_name" placeholder="Apellido" :value="old('last_name')" class="mb-30">
                <x-icons.person-icon />
            </x-auth.field>

            <x-auth.field type="text" name="email" placeholder="Email (Será su usuario)" :value="old('email')" class="mb-30">
                <x-icons.email-icon />
            </x-auth.field>

            <x-auth.field type="password" name="password" placeholder="Password (8 caracteres mínimo)">
                <x-icons.lock-icon />
            </x-auth.field>
        </div>

        <div class="ad-auth-btn">
            <button type="submit" class="btn btn-primary">Registrarse</button>
        </div>

        <p class="ad-register-text">¿ Ya está registrado ? <a href="{{ route('login') }}">Inicie Sesión</a></p>
    </form>

</x-layouts.auth>
