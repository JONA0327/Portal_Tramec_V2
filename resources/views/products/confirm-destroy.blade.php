{{-- Borrado con confirmación escrita: no alcanza con un botón, hay que escribir
     la palabra "confirmar" tal cual. Se valida server-side (ProductsController@destroy),
     no solo en el navegador — es la única forma de que sea una seguridad real. --}}
<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-lg-6">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        Eliminar producto
                    </div>

                    <div class="card-body">
                        <p>
                            Estás por eliminar permanentemente el producto
                            <strong>{{ $product->name }}</strong> ({{ $product->name2 }}).
                            Esta acción no se puede deshacer.
                        </p>

                        @error('confirmacion')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror

                        <form method="POST" action="{{ route('products.destroy', $product) }}">
                            @csrf
                            @method('DELETE')

                            <div class="mb-3">
                                <label class="form-label">
                                    Para confirmar, escribí la palabra <strong>confirmar</strong> abajo:
                                </label>
                                <input type="text" name="confirmacion" class="form-control" autocomplete="off" required autofocus>
                            </div>

                            <button type="submit" class="btn btn-danger">Eliminar definitivamente</button>
                            <a href="{{ route('products.show', $product) }}" class="btn btn-secondary">Cancelar</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layouts.dashboard>
