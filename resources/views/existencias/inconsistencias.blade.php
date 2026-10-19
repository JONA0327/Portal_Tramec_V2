{{-- Fila que pasó a tener conflicto: alguien editó una cantidad a mano y, desde
     entonces, el ERP volvió a traer un valor distinto al que tenía en ese momento
     (ver ExistenciaOverride::tieneConflicto()). Ninguno de los dos valores se pisa
     solo -- queda acá hasta que alguien decida cuál vale. --}}
<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Inconsistencias de Existencias</span>
                        <a href="{{ route('existencias') }}" class="btn btn-sm btn-secondary">Volver a Existencias</a>
                    </div>

                    <div class="card-body">
                        @if(session('status'))
                            <div class="alert alert-success">{{ session('status') }}</div>
                        @endif

                        @if($pendientes->isEmpty())
                            <p class="mb-0">No hay inconsistencias pendientes.</p>
                        @else
                            <p class="text-muted">
                                Estos productos tienen una cantidad editada a mano cuyo ERP cambió después de esa edición.
                                Elegí si se mantiene el ajuste manual o se acepta el valor actual del ERP.
                            </p>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Nombre</th>
                                            <th>Almacén</th>
                                            <th>Cantidad manual (mostrada)</th>
                                            <th>ERP al momento de editar</th>
                                            <th>ERP actual</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pendientes as $r)
                                            <tr>
                                                <td>{{ $r['codigo'] }}</td>
                                                <td>{{ $r['nombre'] }}</td>
                                                <td>{{ $r['override']->id_almacen }} ({{ $r['override']->empresa }})</td>
                                                <td>{{ rtrim(rtrim(number_format((float) $r['override']->cantidad_manual, 2, '.', ','), '0'), '.') }}</td>
                                                <td>{{ rtrim(rtrim(number_format((float) $r['override']->cantidad_erp_al_editar, 2, '.', ','), '0'), '.') }}</td>
                                                <td>{{ $r['cantidad_actual_erp'] !== null ? rtrim(rtrim(number_format($r['cantidad_actual_erp'], 2, '.', ','), '0'), '.') : 'sin dato' }}</td>
                                                <td class="text-nowrap">
                                                    <form method="POST" action="{{ route('existencias.inconsistencias.mantener', $r['override']) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-primary">Mantener manual</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('existencias.inconsistencias.aceptar-erp', $r['override']) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Aceptar ERP</button>
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
