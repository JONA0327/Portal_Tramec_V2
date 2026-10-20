<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                        <span>
                            Usuarios
                            @if($pendientes > 0)
                                <a href="{{ route('users.index', ['estado' => 'inactivos']) }}" class="badge bg-warning text-dark text-decoration-none">{{ $pendientes }} inactivo(s) / pendiente(s)</a>
                            @endif
                        </span>
                        <a href="{{ route('users.create') }}" class="btn btn-sm btn-primary">Nuevo usuario</a>
                    </div>

                    <div class="card-body">
                        @if(session('status'))
                            <div class="alert alert-success">{{ session('status') }}</div>
                        @endif
                        @if(session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif

                        <form method="GET" action="{{ route('users.index') }}" class="mb-3 d-flex flex-wrap" style="gap: 8px;">
                            <input type="text" name="buscar" value="{{ $buscar }}" class="form-control" style="max-width: 280px;" placeholder="Nombre o correo" autocomplete="off">
                            <select name="estado" class="form-control" style="max-width: 160px;" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                <option value="activos" @selected($estado === 'activos')>Activos</option>
                                <option value="inactivos" @selected($estado === 'inactivos')>Inactivos</option>
                            </select>
                            <select name="nivel" class="form-control" style="max-width: 260px;" onchange="this.form.submit()">
                                <option value="">Todos los niveles</option>
                                <option value="sin" @selected($nivel === 'sin')>Sin nivel</option>
                                @foreach(\App\Models\PriceRule::NIVELES as $n => $label)
                                    <option value="{{ $n }}" @selected($nivel === (string) $n)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-secondary">Buscar</button>
                        </form>

                        @if($users->isEmpty())
                            <p class="mb-0">No se encontraron usuarios.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Correo</th>
                                            <th>Nivel</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($users as $u)
                                            @php $activo = (int) $u->active_flag === 1; @endphp
                                            <tr class="{{ $activo ? '' : 'table-warning' }}">
                                                <td>{{ $u->first_name }} {{ $u->last_name }}</td>
                                                <td>{{ $u->email }}</td>
                                                <td>
                                                    <form method="POST" action="{{ route('users.quick', $u) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <select name="level" class="form-control form-control-sm" style="min-width: 220px;" onchange="this.form.submit()" aria-label="Nivel de {{ $u->first_name }}">
                                                            @if($u->level === null || ! array_key_exists((int) $u->level, \App\Models\PriceRule::NIVELES))<option value="" selected>— Sin nivel{{ $u->level !== null ? ' (nivel '.$u->level.' retirado)' : '' }} —</option>@endif
                                                            @foreach(\App\Models\PriceRule::NIVELES as $n => $label)
                                                                <option value="{{ $n }}" @selected($u->level !== null && (int) $u->level === $n)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </form>
                                                </td>
                                                <td>{{ $activo ? 'Activo' : 'Inactivo' }}</td>
                                                <td class="text-nowrap">
                                                    <form method="POST" action="{{ route('users.quick', $u) }}" class="d-inline">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="active_flag" value="{{ $activo ? 0 : 1 }}">
                                                        <button type="submit" class="btn btn-sm {{ $activo ? 'btn-outline-secondary' : 'btn-success' }}">{{ $activo ? 'Desactivar' : 'Activar' }}</button>
                                                    </form>
                                                    <a href="{{ route('users.edit', $u) }}" class="btn btn-sm btn-primary">Editar</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4">{{ $users->links('pagination::bootstrap-5') }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layouts.dashboard>
