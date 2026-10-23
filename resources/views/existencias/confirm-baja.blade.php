{{-- Mismo patrón de confirmación escrita que products/confirm-destroy.blade.php --}}
<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-lg-6">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        Dar de baja producto
                    </div>

                    <div class="card-body">
                        <p>
                            Estás por dar de baja el producto <strong>{{ $codigo }}</strong> ({{ $nombre }}) en
                            <strong>todos los almacenes</strong>. Sale de Productos y de Existencias y, aunque CONTPAQi lo siga
                            enviando, el portal lo ignora (no aparecerá como "nuevo"). No borra nada ni modifica CONTPAQi:
                            queda en la página <strong>Bajas</strong>, desde donde puedes reactivarlo.
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
