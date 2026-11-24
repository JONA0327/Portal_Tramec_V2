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
            @if($user->exists)
            <div class="col-12 col-lg-6 mt-3 mt-lg-0">
                <div class="card">
                    <div class="card-header">Acceso por API</div>
                    <div class="card-body">
                        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
                        @if(session('api_token'))
                            <div class="alert alert-warning">
                                <strong>Copia el token ahora: no se vuelve a mostrar.</strong>
                                <input type="text" class="form-control mt-2" value="{{ session('api_token') }}" readonly onclick="this.select()">
                            </div>
                        @endif
                        <div class="small text-muted mb-3">
                            <strong>Cómo usarlo</strong>
                            <ol class="pl-3 mb-2">
                                <li>Escribe para qué es y pulsa <em>Generar token</em>. Se muestra una sola vez: cópialo y pásalo a quien lo va a usar.</li>
                                <li>El tercero lo manda en cada petición en el encabezado <code>Authorization: Bearer &lt;token&gt;</code>.</li>
                                <li>Verá los productos y precios de <strong>esta cuenta</strong>: su nivel y sus reglas de precio. Si desactivas la cuenta o revocas el token, pierde el acceso.</li>
                            </ol>
                            <strong>Rutas</strong> (GET, base <code>{{ url('/api') }}</code>):
                            <ul class="pl-3 mb-2">
                                <li><code>/products</code>: todos los productos con su precio de lista</li>
                                <li><code>/product_by_id/{id}</code>, <code>/product_by_name/{nombre}</code>, <code>/products_by_brand/{marca}</code>, <code>/product_list_price/{id}</code></li>
                                <li><code>/stocks</code>, <code>/stocks_by_warehouse/{id}</code>, <code>/stocks_by_id/{id}</code>: existencias</li>
                                <li>Cuentas del equipo Tramec: las mismas de productos con <code>_tmc</code> al final (<code>/products_tmc</code>…), con los 5 precios</li>
                            </ul>
                            <strong>Ejemplo:</strong> <code>curl -H "Authorization: Bearer TU_TOKEN" {{ url('/api/products') }}</code><br>
                            Responde JSON: <code>{"success": true, "data": [...], "message": "..."}</code>
                        </div>
                        <form method="POST" action="{{ route('users.api-tokens.store', $user) }}" class="d-flex" style="gap: 8px;">
                            @csrf
                            <input type="text" name="nombre" class="form-control" maxlength="60" placeholder="Para qué es (ej. ERP del cliente)" required aria-label="Nombre del token">
                            <button type="submit" class="btn btn-primary text-nowrap">Generar token</button>
                        </form>
                        @if($tokens->isNotEmpty())
                            <table class="table table-sm mt-3 mb-0">
                                <thead><tr><th>Nombre</th><th>Creado</th><th>Último uso</th><th></th></tr></thead>
                                <tbody>
                                    @foreach($tokens as $t)
                                        <tr>
                                            <td>{{ $t->nombre }}</td>
                                            <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($t->created_at)->format('d/m/Y') }}</td>
                                            <td class="text-nowrap">{{ $t->last_used_at ? \Illuminate\Support\Carbon::parse($t->last_used_at)->format('d/m/Y H:i') : 'Nunca' }}</td>
                                            <td class="text-end">
                                                <form method="POST" action="{{ route('users.api-tokens.destroy', [$user, $t->id]) }}" onsubmit="return confirm('¿Revocar este token? Quien lo use dejará de tener acceso.')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-limpiar">Revocar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

</x-layouts.dashboard>
