<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-xl-8">
                <div class="card">
                    <div class="card-header">Revisar importación de Excel</div>

                    <div class="card-body">
                        <p>
                            Archivo <strong>{{ $p['archivo'] }}</strong> → almacén <strong>{{ $p['almacen'] }}</strong> ({{ $p['empresa'] }}).
                        </p>
                        <ul>
                            <li><strong>{{ count($p['filas']) }}</strong> referencias válidas
                                @if($p['omitidas'] > 0)
                                    <span class="text-warning">({{ $p['omitidas'] }} fila(s) omitida(s) por cantidad no numérica)</span>
                                @endif
                            </li>
                            <li>{{ $p['en_catalogo'] }} coinciden con el catálogo del ERP: se guardan como cantidad del producto en ese almacén</li>
                            <li>{{ count($p['nuevas']) }} no están en el catálogo del ERP: se agregan solo a Existencias, con su código (no se crean productos)</li>
                            <li><strong>{{ count($p['se_quitan']) }}</strong> existencias "nuevas" de una importación anterior ya no vienen en el archivo y se quitarán</li>
                        </ul>

                        @if($p['nuevas'])
                            <div class="alert alert-info"><strong>Solo en Existencias:</strong> {{ implode(', ', $p['nuevas']) }}</div>
                        @endif
                        @if($p['se_quitan'])
                            <div class="alert alert-warning"><strong>Se quitarán:</strong> {{ implode(', ', $p['se_quitan']) }}</div>
                        @endif

                        <form method="POST" action="{{ route('existencias.importar.confirmar') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary">Confirmar importación</button>
                        </form>
                        <form method="POST" action="{{ route('existencias.importar.cancelar') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary">Cancelar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-layouts.dashboard>
