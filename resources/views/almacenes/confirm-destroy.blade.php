{{-- Borrado con confirmación escrita, mismo patrón que products/confirm-destroy.blade.php --}}
<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-lg-6">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        Eliminar almacén
                    </div>

                    <div class="card-body">
                        <p>
                            Estás por eliminar permanentemente el almacén
                            <strong>{{ $warehouse->name }}</strong>.
                            Esta acción no se puede deshacer.
                        </p>

                        @error('confirmacion')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror

                        <form method="POST" action="{{ route('warehouses.destroy', $warehouse) }}">
                            @csrf
                            @method('DELETE')

                            <div class="mb-3">
                                <label class="form-label">
                                    Para confirmar, escribí la palabra <strong>confirmar</strong> abajo:
                                </label>
                                <input type="text" name="confirmacion" class="form-control" autocomplete="off" required autofocus>
                            </div>

                            <button type="submit" class="btn btn-danger">Eliminar definitivamente</button>
                            <a href="{{ route('warehouses') }}" class="btn btn-secondary">Cancelar</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layouts.dashboard>
