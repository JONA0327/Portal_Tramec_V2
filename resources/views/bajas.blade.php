{{-- Productos dados de baja (solo administradores). Ver BajasController. --}}
<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                        <span>Bajas</span>
                        <small class="text-muted">{{ $bajas->total() }} producto(s) dado(s) de baja</small>
                    </div>

                    <div class="card-body">
                        @if(session('status'))
                            <div class="alert alert-success">{{ session('status') }}</div>
                        @endif

                        <p class="text-muted">
                            Los productos dados de baja no aparecen en Productos ni en Existencias, y aunque CONTPAQi los siga
                            enviando el portal los ignora (no salen como "nuevos"). Esto no modifica CONTPAQi. Reactívalos aquí si los necesitas de nuevo.
                        </p>

                        <form method="GET" action="{{ route('bajas') }}" class="filtros-barra">
                            <input type="hidden" name="por_pagina" value="{{ $perPage }}">
                            <input type="text" name="buscar" value="{{ $buscar }}" class="form-control filtro-buscar" placeholder="Buscar por código o nombre..." autocomplete="off">
                            <button type="submit" class="btn btn-primary">Buscar</button>
                            @if($buscar !== '')
                                <a href="{{ route('bajas') }}" class="btn btn-outline-secondary">Limpiar</a>
                            @endif
                        </form>

                        <x-por-pagina :actual="$perPage" />

                        @if($bajas->isEmpty())
                            <p class="mb-0">{{ $buscar !== '' ? 'No se encontraron bajas con esa búsqueda.' : 'No hay productos dados de baja.' }}</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Nombre</th>
                                            <th>Empresa</th>
                                            <th>CONTPAQi</th>
                                            <th>Dado de baja</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($bajas as $b)
                                            <tr>
                                                <td>{{ $b->codigo }}</td>
                                                <td>{{ $b->nombre ?? '-' }}</td>
                                                <td>{{ $b->empresa === '*' ? 'Cualquiera' : $b->empresa }}</td>
                                                <td>
                                                    @if($b->sigue_en_erp)
                                                        <span class="badge bg-info text-dark" title="CONTPAQi lo sigue devolviendo; el portal lo ignora">Sigue en CONTPAQi</span>
                                                    @else
                                                        <span class="text-muted">Ya no está</span>
                                                    @endif
                                                </td>
                                                <td class="text-nowrap">
                                                    {{ $b->created_at?->format('d/m/Y H:i') }}
                                                    @if($b->dado_de_baja_por && isset($usuarios[$b->dado_de_baja_por]))
                                                        <br><small class="text-muted">{{ $usuarios[$b->dado_de_baja_por]->first_name }} {{ $usuarios[$b->dado_de_baja_por]->last_name }}</small>
                                                    @endif
                                                </td>
                                                <td class="text-nowrap">
                                                    <form method="POST" action="{{ route('bajas.reactivar', $b) }}" class="d-inline"
                                                        onsubmit="return confirm(@js('¿Reactivar '.$b->codigo.'? Vuelve a Productos y Existencias.'))">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success">Reactivar</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4">{{ $bajas->links('pagination::bootstrap-5') }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layouts.dashboard>
