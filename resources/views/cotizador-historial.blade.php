{{-- Historial de cotizaciones guardadas (una fila por moneda) con la opción de volver a descargar el PDF. --}}
<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                        <span>Historial de cotizaciones</span>
                        <a href="{{ route('cotizador') }}" class="btn btn-sm btn-primary">Ir al carrito cotizador</a>
                    </div>

                    <div class="card-body">
                        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

                        <form method="GET" action="{{ route('cotizador.historial') }}" class="filtros-barra">
                            <input type="hidden" name="por_pagina" value="{{ $porPagina }}">
                            <input type="text" name="folio" value="{{ $filtros['folio'] }}" class="form-control" placeholder="Folio" aria-label="Folio" autocomplete="off">
                            <input type="text" name="cliente" value="{{ $filtros['cliente'] }}" class="form-control" placeholder="Cliente" aria-label="Cliente" autocomplete="off">
                            <input type="date" name="fecha" value="{{ $filtros['fecha'] }}" class="form-control" aria-label="Fecha">
                            <div class="grupo-busqueda" style="flex: 0 1 auto;">
                                <button type="submit" class="btn btn-primary">Buscar</button>
                                <a href="{{ route('cotizador.historial') }}" class="btn btn-limpiar">Limpiar</a>
                            </div>
                        </form>

                        @if($registros->isEmpty())
                            <p class="mb-0 text-muted">{{ array_filter($filtros) ? 'No se encontraron cotizaciones con esos filtros.' : 'Todavía no hay cotizaciones guardadas. Al descargar un PDF en el carrito, marca "Guardar esta cotización en el historial".' }}</p>
                        @else
                            <x-por-pagina :actual="$porPagina" />
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle">
                                    <thead>
                                        <tr>
                                            <th>Folio</th>
                                            <th>Fecha</th>
                                            <th>Cliente</th>
                                            <th>Productos</th>
                                            <th class="text-end">Total</th>
                                            @if($verUsuario)<th>Guardada por</th>@endif
                                            <th>Guardada</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($registros as $r)
                                            <tr>
                                                <td class="text-nowrap"><strong>{{ $r->folio ?: 'Sin folio' }}</strong></td>
                                                <td class="text-nowrap">{{ $r->fecha?->format('d/m/Y') }}</td>
                                                <td>{{ $r->cliente_empresa ?: '—' }}</td>
                                                <td>{{ count($r->datos['items'] ?? []) }}</td>
                                                <td class="text-end text-nowrap">{{ number_format($r->total, 2) }} {{ $r->moneda }}</td>
                                                @if($verUsuario)<td>{{ $r->user ? trim($r->user->first_name.' '.$r->user->last_name) : '—' }}</td>@endif
                                                <td class="text-nowrap">{{ $r->created_at?->format('d/m/Y H:i') }}</td>
                                                <td class="text-nowrap">
                                                    <a href="{{ route('cotizador.historial.pdf', $r) }}" class="btn btn-sm btn-primary">Descargar PDF</a>
                                                    <form method="POST" action="{{ route('cotizador.historial.editar', $r) }}" class="d-inline"
                                                        @if($carritoItems > 0) onsubmit="return confirm(@js('El carrito tiene '.$carritoItems.' producto(s): se vaciará y se llenará con esta cotización. ¿Continuar?'))" @endif>
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-secondary" title="Vacía el carrito y lo llena con esta cotización para generarla de nuevo">Editar</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('cotizador.historial.eliminar', $r) }}" class="d-inline"
                                                        onsubmit="return confirm(@js('¿Eliminar la cotización '.($r->folio ?: 'sin folio').' del historial?'))">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-limpiar">Eliminar</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            {{ $registros->links('vendor.pagination.portal') }}
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layouts.dashboard>
