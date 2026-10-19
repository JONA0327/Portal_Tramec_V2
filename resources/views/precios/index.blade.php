<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                        <span>Descuentos y aumentos</span>
                        <a href="{{ route('price-rules.create') }}" class="btn btn-sm btn-primary">Nueva regla</a>
                    </div>

                    <div class="card-body">
                        <x-stock-status />
                        @if(session('status'))
                            <div class="alert alert-success">{{ session('status') }}</div>
                        @endif

                        <p class="text-muted">
                            Las reglas solo cambian el precio que se <strong>muestra</strong> en Existencias y Productos;
                            los precios originales guardados no se modifican. Si varias reglas coinciden con un producto,
                            se aplican una tras otra en el orden de esta lista.
                        </p>

                        @if($rules->isEmpty())
                            <p class="mb-0">Todavía no hay reglas.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Nombre</th>
                                            <th>Cambio</th>
                                            <th>Productos</th>
                                            <th>Precios</th>
                                            <th>Niveles / usuarios</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rules as $rule)
                                            <tr class="{{ $rule->activo ? '' : 'text-muted' }}">
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $rule->nombre }}</td>
                                                <td class="text-nowrap">
                                                    <span class="badge {{ $rule->tipo === 'descuento' ? 'bg-success' : 'bg-warning text-dark' }}">
                                                        {{ $rule->tipo === 'descuento' ? '−' : '+' }}{{ rtrim(rtrim(number_format($rule->porcentaje, 2), '0'), '.') }}%
                                                    </span>
                                                </td>
                                                <td>
                                                    @if(trim((string) $rule->buscar) === '')
                                                        Todos ({{ $counts[$rule->id] }})
                                                    @else
                                                        «{{ $rule->buscar }}» en {{ $rule->campo === 'ambos' ? 'código o nombre' : $rule->campo }}
                                                        <small class="text-muted">({{ $counts[$rule->id] }} de {{ $totalCatalog }})</small>
                                                    @endif
                                                </td>
                                                <td>{{ collect($rule->columnas)->map(fn ($c) => \App\Models\PriceRule::COLUMNAS[$c] ?? $c)->implode(', ') }}</td>
                                                <td>
                                                    {{ $rule->niveles ? collect($rule->niveles)->map(fn ($n) => \App\Models\PriceRule::NIVELES[$n] ?? $n)->implode(', ') : ($rule->usuarios ? '' : 'Todos') }}
                                                    @if($rule->usuarios)
                                                        <div class="small">Usuarios: {{ collect($rule->usuarios)->map(fn ($id) => isset($usuariosPorId[$id]) ? $usuariosPorId[$id]->first_name.' '.$usuariosPorId[$id]->last_name : "#$id")->implode(', ') }}</div>
                                                    @endif
                                                </td>
                                                <td>{{ $rule->activo ? 'Activa' : 'Inactiva' }}</td>
                                                <td class="text-nowrap">
                                                    <a href="{{ route('price-rules.edit', $rule) }}" class="btn btn-sm btn-primary">Editar</a>
                                                    <form method="POST" action="{{ route('price-rules.toggle', $rule) }}" class="d-inline">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $rule->activo ? 'Desactivar' : 'Activar' }}</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('price-rules.destroy', $rule) }}" class="d-inline"
                                                        onsubmit="return confirm(@js('¿Eliminar la regla «'.$rule->nombre.'»?'))">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layouts.dashboard>
