<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Editar: {{ $product->name }}</span>
                        <a href="{{ route('products.show', $product) }}" class="btn btn-sm btn-secondary">Cancelar</a>
                    </div>

                    <div class="card-body">
                        {{-- name/name2/idERP no aparecen como inputs a propósito: los mantiene al
                             día el ERP automáticamente (App\Listeners\SyncLocalProductFromErp),
                             nunca se editan a mano. --}}
                        <div class="alert alert-secondary">
                            <strong>{{ $product->name }}</strong>
                            @if($product->isMatched())
                                — ERP: <strong>#{{ $product->idERP }}</strong> (emparejado automáticamente)
                            @else
                                — <strong class="text-warning">⚠ Sin emparejar</strong>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('products.update', $product) }}">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label class="form-label">Descripción corta</label>
                                <input type="text" name="description_short" value="{{ old('description_short', $product->description_short) }}" class="form-control">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Descripción del proveedor</label>
                                <input type="text" name="description_supplier" value="{{ old('description_supplier', $product->description_supplier) }}" class="form-control">
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Marca</label>
                                    <input type="text" name="brand" value="{{ old('brand', $product->brand) }}" class="form-control">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Tipo de producto</label>
                                    <input type="text" name="kind_of_product" value="{{ old('kind_of_product', $product->kind_of_product) }}" class="form-control">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Código de clasificación</label>
                                    <input type="number" name="clasification_code" value="{{ old('clasification_code', $product->clasification_code) }}" class="form-control">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Moneda base</label>
                                    <input type="text" name="base_currency" value="{{ old('base_currency', $product->base_currency) }}" class="form-control">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Rotación</label>
                                    <input type="text" name="rotation" value="{{ old('rotation', $product->rotation) }}" class="form-control">
                                </div>
                                <div class="col-md-4 mb-3 d-flex align-items-end">
                                    <div class="form-check">
                                        <input type="checkbox" name="active" value="1" id="active" class="form-check-input"
                                            @checked(old('active', $product->active))>
                                        <label for="active" class="form-check-label">Activo</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                @for($i = 1; $i <= 5; $i++)
                                    <div class="col-md-2 mb-3">
                                        <label class="form-label">Característica {{ $i }}</label>
                                        <input type="text" name="characteristic{{ $i }}" value="{{ old("characteristic$i", $product->{"characteristic$i"}) }}" class="form-control">
                                    </div>
                                @endfor
                            </div>

                            <div class="row">
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">Precio de lista</label>
                                    <input type="number" step="0.01" name="list_price" value="{{ old('list_price', $product->list_price) }}" class="form-control">
                                </div>
                                @for($i = 1; $i <= 5; $i++)
                                    <div class="col-md-2 mb-3">
                                        <label class="form-label">Precio {{ $i }}</label>
                                        <input type="number" step="0.01" name="price{{ $i }}" value="{{ old("price$i", $product->{"price$i"}) }}" class="form-control">
                                    </div>
                                @endfor
                            </div>

                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layouts.dashboard>
