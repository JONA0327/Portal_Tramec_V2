<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12 col-xl-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>{{ $rule->exists ? 'Editar regla' : 'Nueva regla' }}</span>
                        <a href="{{ route('price-rules.index') }}" class="btn btn-sm btn-secondary">Cancelar</a>
                    </div>

                    <div class="card-body">
                        @if($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ $rule->exists ? route('price-rules.update', $rule) : route('price-rules.store') }}">
                            @csrf
                            @if($rule->exists) @method('PUT') @endif

                            <div class="mb-3">
                                <label class="form-label" for="nombre">Nombre</label>
                                <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $rule->nombre) }}" class="form-control" maxlength="120" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="tipo">Tipo</label>
                                    <select id="tipo" name="tipo" class="form-control">
                                        <option value="descuento" @selected(old('tipo', $rule->tipo) === 'descuento')>Descuento (baja el precio)</option>
                                        <option value="aumento" @selected(old('tipo', $rule->tipo) === 'aumento')>Aumento (sube el precio)</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="porcentaje">Porcentaje</label>
                                    <div class="input-group">
                                        <input type="number" id="porcentaje" name="porcentaje" value="{{ old('porcentaje', $rule->porcentaje) }}" class="form-control" step="0.01" min="0.01" max="100" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label" for="buscar">Productos a los que aplica</label>
                                    <input type="text" id="buscar" name="buscar" value="{{ old('buscar', $rule->buscar) }}" class="form-control" maxlength="255" placeholder="Ej.: C2000, DELTA   (vacío = todos los productos)" autocomplete="off">
                                    <div class="form-text">Separá varios términos con coma: coincide si el producto contiene cualquiera de ellos.</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label" for="campo">Buscar en</label>
                                    <select id="campo" name="campo" class="form-control">
                                        <option value="ambos" @selected(old('campo', $rule->campo) === 'ambos')>Código o descripción</option>
                                        <option value="codigo" @selected(old('campo', $rule->campo) === 'codigo')>Solo código</option>
                                        <option value="nombre" @selected(old('campo', $rule->campo) === 'nombre')>Solo descripción</option>
                                    </select>
                                </div>
                            </div>

                            <div id="preview" class="alert alert-light border mb-3" aria-live="polite">Calculando coincidencias…</div>

                            <div class="mb-3">
                                <label class="form-label d-block">Precios que se modifican</label>
                                @php $columnas = old('columnas', $rule->columnas ?? []); @endphp
                                @foreach(\App\Models\PriceRule::COLUMNAS as $key => $label)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="columnas[]" id="col_{{ $key }}" value="{{ $key }}" @checked(in_array($key, $columnas, true))>
                                        <label class="form-check-label" for="col_{{ $key }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mb-3">
                                <label class="form-label d-block">Niveles de usuario que ven este cambio</label>
                                @php $niveles = array_map('intval', old('niveles', $rule->niveles ?? [])); @endphp
                                @foreach(\App\Models\PriceRule::NIVELES as $n => $label)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="niveles[]" id="nivel_{{ $n }}" value="{{ $n }}" @checked(in_array($n, $niveles, true))>
                                        <label class="form-check-label" for="nivel_{{ $n }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                                <div class="form-text">Sin marcar ninguno = lo ven todos los niveles.</div>
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" name="activo" id="activo" value="1" @checked(old('activo', $rule->activo))>
                                <label class="form-check-label" for="activo">Regla activa</label>
                            </div>

                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var buscar = document.getElementById('buscar'), campo = document.getElementById('campo'),
                box = document.getElementById('preview'), t;

            function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

            function refresh() {
                var url = @json(route('price-rules.preview')) + '?buscar=' + encodeURIComponent(buscar.value) + '&campo=' + campo.value;
                fetch(url, {headers: {Accept: 'application/json'}}).then(function (r) { return r.json(); }).then(function (d) {
                    box.innerHTML = '<strong>' + d.total + '</strong> producto(s) coinciden'
                        + (d.items.length ? ':<ul class="mb-0 small">' + d.items.map(function (i) { return '<li>' + esc(i) + '</li>'; }).join('')
                        + (d.total > d.items.length ? '<li>…</li>' : '') + '</ul>' : '.');
                }).catch(function () { box.textContent = 'No se pudo calcular la vista previa.'; });
            }

            buscar.addEventListener('input', function () { clearTimeout(t); t = setTimeout(refresh, 300); });
            campo.addEventListener('change', refresh);
            refresh();
        })();
    </script>

</x-layouts.dashboard>
