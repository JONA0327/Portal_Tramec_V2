<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Editar: {{ $codigo }}</span>
                        <a href="{{ route('existencias') }}" class="btn btn-sm btn-secondary">Cancelar</a>
                    </div>

                    <div class="card-body">
                        <p class="text-muted mb-3">{{ $nombre }}</p>

                        @php
                            $original = $cantidadErp ?? ($cantidadManual !== null ? (float) $cantidadManual : null);
                            $texto = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ','), '0'), '.') ?: '0';
                        @endphp
                        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

                        <div class="alert alert-secondary">
                            Cantidad original: <strong>{{ $original !== null ? $texto($original) : 'sin dato' }}</strong>
                            <small>({{ $cantidadErp !== null ? 'CONTPAQi' : 'Excel de Schenker' }})</small>
                            @if($cantidadTemporal !== null)
                                <br>Cantidad temporal en uso: <strong>{{ $texto($cantidadTemporal) }}</strong>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('existencias.cantidad', [$empresa, $idProducto, $idAlmacen]) }}" class="mb-2">
                            @csrf
                            <label class="form-label" for="cantidad">Cantidad temporal</label>
                            <div class="d-flex" style="gap: 8px;">
                                <input type="number" id="cantidad" name="cantidad" class="form-control" min="0" step="any" required
                                    value="{{ old('cantidad', $cantidadTemporal !== null ? (float) $cantidadTemporal : $original) }}">
                                <button type="submit" class="btn btn-primary">Guardar</button>
                            </div>
                            <div class="form-text">No cambia CONTPAQi. Se mantiene hasta que la resincronices; en 0 deja de mostrarse.</div>
                        </form>
                        @if($cantidadTemporal !== null)
                            <form method="POST" action="{{ route('existencias.resincronizar', [$empresa, $idProducto, $idAlmacen]) }}" class="mb-3">
                                @csrf
                                <button type="submit" class="btn btn-secondary">Resincronizar (quitar el cambio)</button>
                            </form>
                        @endif
                        <hr>

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
