<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Editar cantidad: {{ $codigo }}</span>
                        <a href="{{ route('existencias') }}" class="btn btn-sm btn-secondary">Cancelar</a>
                    </div>

                    <div class="card-body">
                        <p class="text-muted mb-3">{{ $nombre }}</p>

                        {{-- Este ajuste queda guardado SOLO en el portal (tabla
                             existencia_overrides), nunca se manda al ERP. Si el ERP
                             vuelve a cambiar esta cantidad después de guardar acá, va a
                             aparecer en "Inconsistencias" para decidir cuál vale. --}}
                        <div class="alert alert-secondary">
                            Cantidad actual en el ERP:
                            <strong>{{ $cantidadErp !== null ? rtrim(rtrim(number_format($cantidadErp, 2, '.', ','), '0'), '.') : 'sin dato' }}</strong>
                        </div>

                        @error('cantidad')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror

                        <form method="POST" action="{{ route('existencias.update', [$empresa, $idProducto, $idAlmacen]) }}">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label class="form-label">Cantidad manual</label>
                                <input type="number" step="0.01" min="0" name="cantidad" class="form-control"
                                    value="{{ old('cantidad', $cantidadManual ?? $cantidadErp) }}" required autofocus>
                            </div>

                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </form>

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
