<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>{{ $user->exists ? 'Editar usuario' : 'Nuevo usuario' }}</span>
                        <a href="{{ route('users.index') }}" class="btn btn-sm btn-secondary">Cancelar</a>
                    </div>

                    <div class="card-body">
                        @if($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
                            @csrf
                            @if($user->exists) @method('PUT') @endif

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
                                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" maxlength="255" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="password">Contraseña</label>
                                <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" @required(! $user->exists)>
                                @if($user->exists)<div class="form-text">Dejala vacía para no cambiarla.</div>@endif
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="level">Nivel</label>
                                <select id="level" name="level" class="form-control" required>
                                    <option value="" @selected(old('level', $user->level) === null)>— Elegir —</option>
                                    @foreach(\App\Models\PriceRule::NIVELES as $n => $label)
                                        <option value="{{ $n }}" @selected(old('level', $user->level) !== null && (int) old('level', $user->level) === $n)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-check mb-4">
                                <input type="hidden" name="active_flag" value="0">
                                <input class="form-check-input" type="checkbox" name="active_flag" id="active_flag" value="1" @checked(old('active_flag', $user->active_flag))>
                                <label class="form-check-label" for="active_flag">Activo (puede entrar al portal)</label>
                            </div>

                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layouts.dashboard>
