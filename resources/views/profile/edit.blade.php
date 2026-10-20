{{-- Perfil del usuario con el mismo diseño del panel (antes usaba el layout de Breeze/Tailwind y se salía del diseño). --}}
<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-xl-6">
                <div class="card">
                    <div class="card-header">Mi perfil</div>
                    <div class="card-body">
                        @if(session('status') === 'profile-updated')
                            <div class="alert alert-success">Datos guardados.</div>
                        @endif

                        @if($errors->hasAny(['first_name', 'last_name', 'email']))
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach($errors->only(['first_name', 'last_name', 'email']) as $messages)
                                        @foreach((array) $messages as $m)<li>{{ $m }}</li>@endforeach
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('profile.update') }}">
                            @csrf
                            @method('PATCH')

                            <div class="mb-3">
                                <label class="form-label" for="first_name">Nombre</label>
                                <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" class="form-control" maxlength="255" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="last_name">Apellido</label>
                                <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" class="form-control" maxlength="255" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="email">Correo</label>
                                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" maxlength="255" required autocomplete="username">
                            </div>
                            <div class="mb-4">
                                <span class="form-label d-block">Nivel</span>
                                {{ \App\Models\PriceRule::NIVELES[(int) $user->level] ?? 'Sin nivel asignado' }}
                            </div>

                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card">
                    <div class="card-header">Cambiar contraseña</div>
                    <div class="card-body">
                        @if(session('status') === 'password-updated')
                            <div class="alert alert-success">Contraseña actualizada.</div>
                        @endif

                        @if($errors->updatePassword->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">@foreach($errors->updatePassword->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('password.update') }}">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label class="form-label" for="current_password">Contraseña actual</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" autocomplete="current-password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="password">Contraseña nueva</label>
                                <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label" for="password_confirmation">Confirmar contraseña nueva</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                            </div>

                            <button type="submit" class="btn btn-primary">Cambiar contraseña</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layouts.dashboard>
