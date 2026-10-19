{{-- Login de v2, mismo diseño visual que v1 (portal-tramec/resources/views/auth/login.blade.php)
     pero dividido en piezas chicas y reusables en vez de un solo archivo largo:
       - x-layouts.auth      -> estructura de la página (imagen + columna de contenido)
       - x-auth.errors       -> caja de errores de validación
       - x-auth.field        -> input con ícono (uno para email, otro para password)
       - x-icons.*            -> los svg de cada ícono
     La lógica de autenticación no cambia: sigue yendo a AuthenticatedSessionController@store
     (Breeze), que ya valida 'email' + 'password' + 'remember'. --}}
<x-layouts.auth>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <a href="/" class="ad-auth-logo">
            <h4 class="text-center">Portal de Existencias</h4>
        </a>

        <h2><span class="primary">¡Bienvenido!</span></h2>
        <p>Introduzca Sus Datos Para Continuar</p>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        <x-auth.errors />

        <div class="ad-auth-form">
            <x-auth.field type="text" name="email" placeholder="Email" :value="old('email')" class="mb-30">
                <x-icons.email-icon />
            </x-auth.field>

            <x-auth.field type="password" name="password" placeholder="Password">
                <x-icons.lock-icon />
            </x-auth.field>
        </div>

        {{-- Widget de reCAPTCHA, igual que en v1. Solo se muestra: el formulario
             todavía no valida "g-recaptcha-response" al hacer login. --}}
        <div class="ad-auth-form">
            {!! NoCaptcha::renderJs() !!}
            {!! NoCaptcha::display() !!}
        </div>

        <div class="ad-other-feilds">
            <div class="ad-checkbox">
                <label>
                    {{-- v1 tenía este checkbox con name="remeber" (typo) y por eso "recordarme"
                         nunca funcionaba: Breeze espera el campo "remember". Se corrige aquí. --}}
                    <input type="checkbox" name="remember" class="ad-checkbox">
                    <span>Recordarme</span>
                </label>
            </div>

            <a class="forgot-pws-btn" href="{{ route('password.request') }}">¿Olvidó su contraseña?</a>
        </div>

        <div class="ad-auth-btn">
            <input type="submit" class="btn btn-primary" value="Iniciar Sesión" />
        </div>

        <p class="ad-register-text">
            ¿Aún no se registra? <a href="{{ route('register') }}">Regístrese aquí</a>
        </p>
    </form>

</x-layouts.auth>
