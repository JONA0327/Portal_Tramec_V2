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

                        @if($registros->isEmpty())
                            <p class="mb-0 text-muted">Todavía no hay cotizaciones guardadas. Al descargar un PDF en el carrito, marca "Guardar esta cotización en el historial".</p>
                        @else
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
                            {{ $registros->links() }}
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layouts.dashboard>
