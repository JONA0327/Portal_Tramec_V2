<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Opciones: {{ $codigo }}</span>
                        <a href="{{ route('existencias') }}" class="btn btn-sm btn-secondary">Cancelar</a>
                    </div>

                    <div class="card-body">
                        <p class="text-muted mb-3">{{ $nombre }}</p>

                        {{-- La cantidad viene del ERP (o del Excel de Schenker) y no se edita desde el portal. --}}
                        <div class="alert alert-secondary">
                            Cantidad:
                            <strong>{{ $cantidadErp !== null ? rtrim(rtrim(number_format($cantidadErp, 2, '.', ','), '0'), '.') : 'sin dato del ERP' }}</strong>
                            <br><small>Viene de CONTPAQi{{ $cantidadErp === null ? ' (o del Excel de Schenker)' : '' }}; no se edita desde el portal.
                            Si llega a cero deja de mostrarse hasta que vuelva a haber existencia.</small>
                        </div>

                        {{-- Ocultar/restaurar no toca la cantidad ni el ERP -- solo si esta
                             fila aparece o no en el listado de Existencias (ver
                             ExistenciasController::ocultar()/restaurar()). --}}
                        @if($oculto)
                            <p class="text-muted mb-2">Esta fila está oculta del listado de Existencias.</p>
                            <form method="POST" action="{{ route('existencias.restaurar', [$empresa, $idProducto, $idAlmacen]) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary">Restaurar al listado</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('existencias.ocultar', [$empresa, $idProducto, $idAlmacen]) }}"
                                onsubmit="return confirm('¿Ocultar esta fila del listado? No afecta al ERP, se puede restaurar después.');">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary">Ocultar del listado</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layouts.dashboard>
