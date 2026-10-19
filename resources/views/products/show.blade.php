<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>{{ $product->name }}</span>
                        <div>
                            <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-primary">Editar</a>
                            <a href="{{ route('products') }}" class="btn btn-sm btn-secondary">Volver</a>
                        </div>
                    </div>

                    <div class="card-body">
                        @if(session('status'))
                            <div class="alert alert-success">{{ session('status') }}</div>
                        @endif

                        <div class="mb-3">
                            @if($product->isMatched())
                                <span class="badge bg-success">
                                    Emparejado con ERP: #{{ $product->idERP }}
                                </span>
                            @else
                                <span class="badge bg-warning text-dark">
                                    ⚠ Sin emparejar con el ERP
                                </span>
                            @endif
                            <small class="text-muted d-block mt-1">
                                El nombre/código lo mantiene al día el ERP automáticamente
                                (<code>products:match-erp</code> + tiempo real) — no se edita a mano.
                            </small>
                        </div>

                        <table class="table table-bordered">
                            <tr><th style="width: 220px;">Código (name)</th><td>{{ $product->name }}</td></tr>
                            <tr><th>Código normalizado (name2)</th><td>{{ $product->name2 }}</td></tr>
                            <tr><th>Descripción corta</th><td>{{ $product->description_short }}</td></tr>
                            <tr><th>Descripción del proveedor</th><td>{{ $product->description_supplier }}</td></tr>
                            <tr><th>Marca</th><td>{{ $product->brand }}</td></tr>
                            <tr><th>Tipo de producto</th><td>{{ $product->kind_of_product }}</td></tr>
                            <tr><th>Código de clasificación</th><td>{{ $product->clasification_code }}</td></tr>
                            <tr><th>Activo</th><td>{{ $product->active ? 'Sí' : 'No' }}</td></tr>
                            <tr><th>Moneda base</th><td>{{ $product->base_currency }}</td></tr>
                            <tr><th>Rotación</th><td>{{ $product->rotation }}</td></tr>
                            <tr><th>Características</th>
                                <td>
                                    {{ collect([$product->characteristic1, $product->characteristic2, $product->characteristic3, $product->characteristic4, $product->characteristic5])->filter()->implode(' · ') ?: '-' }}
                                </td>
                            </tr>
                            <tr><th>Precio de lista</th><td>{{ $product->list_price }}</td></tr>
                            <tr><th>Precio 1</th><td>{{ $product->price1 }}</td></tr>
                            <tr><th>Precio 2</th><td>{{ $product->price2 }}</td></tr>
                            <tr><th>Precio 3</th><td>{{ $product->price3 }}</td></tr>
                            <tr><th>Precio 4</th><td>{{ $product->price4 }}</td></tr>
                            <tr><th>Precio 5</th><td>{{ $product->price5 }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layouts.dashboard>
