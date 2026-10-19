{{-- Catálogo de almacenes: se empareja con el ERP EN VIVO en cada carga
     (WarehouseErpMatcher::match(), ver WarehousesController::index()) — son
     muy pocas filas, así que no hace falta caché ni scheduler como en
     productos. Un nombre editado en CONTPAQi se refleja acá solo, sin botón. --}}
<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                        <span>Almacenes</span>
                        <small class="text-muted">
                            Emparejados: {{ $stats['matched_before'] + $stats['matched_now'] }}
                            @if($stats['renamed'] > 0)
                                &nbsp;·&nbsp;<span class="text-info">{{ $stats['renamed'] }} nombre(s) actualizado(s) desde CONTPAQi</span>
                            @endif
                            @if(count($stats['nuevos']) > 0)
                                &nbsp;·&nbsp;<span class="text-info">🆕 {{ count($stats['nuevos']) }} nuevos</span>
                            @endif
                            @if($stats['unmatched'] > 0)
                                &nbsp;·&nbsp;<span class="text-warning">⚠ {{ $stats['unmatched'] }} sin emparejar</span>
                            @endif
                        </small>
                    </div>

                    <div class="card-body">
                        <x-stock-status :last-sync="$lastSyncAt" />
                        @if(session('status'))
                            <div class="alert alert-success">{{ session('status') }}</div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Empresa</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($warehouses as $row)
                                        <tr>
                                            <td>
                                                @if($row['estado'] === 'nuevo')
                                                    <span class="rounded-circle d-inline-block bg-info" style="width: 8px; height: 8px;" title="Existe en el ERP, todavía sin almacén local"></span>
                                                @elseif($row['estado'] === 'advertencia')
                                                    <span class="rounded-circle d-inline-block bg-warning" style="width: 8px; height: 8px;" title="No se encontró este almacén en el catálogo actual del ERP"></span>
                                                @else
                                                    <span class="rounded-circle d-inline-block bg-success" style="width: 8px; height: 8px;" title="Emparejado con el ERP"></span>
                                                @endif
                                                {{ $row['id_erp'] ?? '-' }}
                                            </td>
                                            <td>{{ $row['nombre'] }}</td>
                                            <td>{{ $row['empresa'] ? strtoupper($row['empresa']) : '-' }}</td>
                                            <td class="text-nowrap">
                                                @if($row['local_id'])
                                                    <a href="{{ route('warehouses.confirm-destroy', $row['local_id']) }}" class="btn btn-sm btn-danger">Borrar</a>
                                                @else
                                                    {{-- Registro de un clic: crea el almacén local ya vinculado al
                                                         id_erp que trae el ERP en este momento. --}}
                                                    <form method="POST" action="{{ route('warehouses.register') }}" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="empresa" value="{{ $row['empresa'] }}">
                                                        <input type="hidden" name="id_erp" value="{{ $row['id_erp'] }}">
                                                        <input type="hidden" name="almacen_erp_id" value="{{ $row['almacen_erp_id'] }}">
                                                        <input type="hidden" name="nombre" value="{{ $row['nombre'] }}">
                                                        <button type="submit" class="btn btn-sm btn-info">Registrar</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layouts.dashboard>
