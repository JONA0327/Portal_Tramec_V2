{{-- Mismo patrón de confirmación escrita que products/confirm-destroy.blade.php --}}
<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-lg-6">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        Dar de baja existencia
                    </div>

                    <div class="card-body">
                        <p>
                            Estás por dar de baja <strong>{{ $codigo }}</strong> ({{ $nombre }})
                            en este almacén. Esto solo la oculta del listado activo de Existencias
                            (queda en "Dados de baja") -- no borra nada ni afecta al ERP.
                        </p>

                        @error('confirmacion')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror

                        <form method="POST" action="{{ route('existencias.dar-de-baja', [$empresa, $idProducto, $idAlmacen]) }}">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label">
                                    Para confirmar, escribí la palabra <strong>confirmar</strong> abajo:
                                </label>
                                <input type="text" name="confirmacion" class="form-control" autocomplete="off" required autofocus>
                            </div>

                            <button type="submit" class="btn btn-danger">Dar de baja</button>
                            <a href="{{ route('existencias') }}" class="btn btn-secondary">Cancelar</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layouts.dashboard>
