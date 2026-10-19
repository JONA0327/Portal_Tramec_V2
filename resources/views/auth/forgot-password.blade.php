{{-- Restaurar contraseña: en v1 esta página usa un estilo distinto al de login/register
     (tarjeta Bootstrap genérica en vez de los campos "ad-auth-*" con ícono). Se replica
     tal cual luce en v1, incluida esa inconsistencia de estilo. --}}
<x-layouts.auth>

    <div class="card-body p-4">
        <h2>Restaurar Contraseña</h2>

        <p class="text-muted">
            Introduzca su correo registrado. <br><br>
            Recibirá un email con un enlace para restaurar la contraseña.
        </p>
        {{-- v1 mostraba aquí, además, el texto en inglés "Reset Password" (venía de una
             traducción sin versión en español). Se deja igual para no cambiar el diseño. --}}
        <p class="text-muted"><br><br>Reset Password</p>

        @if (session('status'))
            <div class="alert alert-success" role="alert">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="form-group">
                <input
                    id="email"
                    type="email"
                    name="email"
                    class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}"
                    required
                    autocomplete="email"
                    autofocus
                    placeholder="Email"
                    value="{{ old('email') }}"
                >

                @if ($errors->has('email'))
                    <div class="invalid-feedback">
                        {{ $errors->first('email') }}
                    </div>
                @endif
            </div>

            <div class="row">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-flat btn-block">
                        Send Password Reset Link
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <p class="ad-register-text">
                        <br><br>
                        <a href="{{ route('login') }}">&lt;&lt;Regresar al Portal</a>
                    </p>
                </div>
            </div>
        </form>
    </div>

</x-layouts.auth>
