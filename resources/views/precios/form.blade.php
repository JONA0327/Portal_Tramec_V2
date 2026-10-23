<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
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

                            <div class="row">
                                <div class="col-lg-6">
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
                                </div>

                                <div class="col-lg-6">
                                <div id="preview" class="border rounded p-2 mb-3" aria-live="polite">
                                    <div id="preview-resumen" class="mb-2">Calculando coincidencias…</div>
                                    <div id="preview-controles" class="d-flex flex-wrap align-items-center mb-2" style="gap: 8px;" hidden>
                                        <input type="search" id="preview-filtro" class="form-control form-control-sm" style="max-width: 240px;" placeholder="Buscar en la lista…" autocomplete="off">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="marcar-todos">Marcar todos</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="marcar-ninguno">Quitar todos</button>
                                    </div>
                                    <div id="preview-lista" class="lista-casillas" style="max-height: 380px; overflow-y: auto;"></div>
                                    <div class="form-text">Marcá los productos a los que aplica la regla. Si no marcás ninguno, aplica a todos los de esta lista.</div>
                                </div>
                                <div id="incluidos-inputs"></div>

                                <div class="mb-3">
                                    <label class="form-label" for="usuarios">Usuarios específicos</label>
                                    <input type="search" id="usuarios-filtro" class="form-control form-control-sm mb-1" placeholder="Filtrar lista…" autocomplete="off">
                                    @php $selUsers = array_map('intval', old('usuarios', $rule->usuarios ?? [])); @endphp
                                    <div id="usuarios-lista" class="lista-casillas border rounded" style="max-height: 300px; overflow-y: auto;">
                                        @foreach($users as $u)
                                            <label data-t="{{ mb_strtolower($u->first_name.' '.$u->last_name.' '.$u->email) }}">
                                                <input type="checkbox" name="usuarios[]" value="{{ $u->id }}" @checked(in_array($u->id, $selUsers, true))>
                                                <span>{{ $u->first_name }} {{ $u->last_name }} — {{ $u->email }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="form-text">Marcá los usuarios a los que aplica. Sin marcar ninguno = no se limita por usuario (solo por nivel).</div>
                                </div>
                                </div>
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

    <style>
        .lista-casillas { font-size: 0.875rem; }
        .lista-casillas label {
            display: flex; align-items: center; gap: 10px; margin: 0; padding: 5px 8px;
            cursor: pointer; user-select: none;
        }
        .lista-casillas label:hover { background: #eaf4ff; }
        .lista-casillas input[type="checkbox"] {
            flex: none; width: 16px; height: 16px; min-width: 16px; padding: 0; margin: 0; cursor: pointer;
        }
    </style>

    <script>
        (function () {
            var buscar = document.getElementById('buscar'), campo = document.getElementById('campo'),
                resumen = document.getElementById('preview-resumen'), lista = document.getElementById('preview-lista'),
                controles = document.getElementById('preview-controles'), filtro = document.getElementById('preview-filtro'),
                form = buscar.form, todos = null, items = [], loaded = false, truncado = '',
                elegidos = new Set(@json(array_values(old('incluidos', $rule->incluidos ?? []))));

            function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

            function updateResumen() {
                var n = items.filter(function (i) { return elegidos.has(i.c.toUpperCase()); }).length;
                resumen.innerHTML = (n
                    ? '<strong>' + n + '</strong> de ' + items.length + ' producto(s) elegido(s): la regla aplica solo a ellos'
                    : 'Sin selección: la regla aplica a los <strong>' + items.length + '</strong> producto(s) que coinciden')
                    + truncado;
            }

            function render() {
                var q = filtro.value;
                lista.innerHTML = items.map(function (i) {
                    var texto = (i.c + ' ' + i.n).toLowerCase();
                    return '<label data-t="' + esc(texto) + '"' + (q.trim() && !coincide(texto, q) ? ' hidden' : '') + '>'
                        + '<input type="checkbox" data-c="' + esc(i.c.toUpperCase()) + '"' + (elegidos.has(i.c.toUpperCase()) ? ' checked' : '') + '>'
                        + '<span>' + esc(i.c) + (i.n && i.n !== i.c ? ' — ' + esc(i.n) : '') + '</span></label>';
                }).join('');
                controles.hidden = items.length === 0;
                updateResumen();
            }

            // Misma regla que App\Support\Search: cada palabra debe aparecer, en cualquier orden.
            function coincide(texto, q) {
                texto = texto.toLowerCase();
                return q.toLowerCase().split(/\s+/).filter(Boolean).every(function (w) { return texto.indexOf(w) >= 0; });
            }

            // Misma regla que PriceRule::matchesFilter: términos separados por coma (cualquiera), vacío = todos.
            function aplicaFiltro(i) {
                var terms = buscar.value.split(',').map(function (x) { return x.trim(); }).filter(Boolean);
                if (!terms.length) { return true; }
                var texto = campo.value === 'codigo' ? i.c : campo.value === 'nombre' ? i.n : i.c + ' ' + i.n;
                return terms.some(function (term) { return coincide(texto, term); });
            }

            // Filtra al instante, mientras se escribe, sobre el catálogo ya cargado (una sola descarga).
            function refresh() {
                if (!todos) { return; }
                items = todos.filter(aplicaFiltro);
                render();
            }

            fetch(@json(route('price-rules.preview')) + '?buscar=&campo=ambos', {headers: {Accept: 'application/json'}})
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    todos = d.items; loaded = true;
                    truncado = d.total > todos.length ? ' <span class="text-muted">(catálogo recortado a ' + todos.length + ' de ' + d.total + ')</span>' : '';
                    refresh();
                })
                .catch(function () { resumen.textContent = 'No se pudo cargar el catálogo para la vista previa.'; });

            lista.addEventListener('change', function (e) {
                var c = e.target.getAttribute('data-c');
                if (c === null) { return; }
                e.target.checked ? elegidos.add(c) : elegidos.delete(c);
                updateResumen();
            });
            function marcarVisibles(on) {
                Array.prototype.forEach.call(lista.querySelectorAll('label:not([hidden]) input'), function (cb) {
                    cb.checked = on; on ? elegidos.add(cb.getAttribute('data-c')) : elegidos.delete(cb.getAttribute('data-c'));
                });
                updateResumen();
            }
            document.getElementById('marcar-todos').addEventListener('click', function () { marcarVisibles(true); });
            document.getElementById('marcar-ninguno').addEventListener('click', function () { marcarVisibles(false); });
            filtro.addEventListener('input', function () {
                var q = this.value;
                Array.prototype.forEach.call(lista.children, function (d) { d.hidden = q.trim() !== '' && !coincide(d.getAttribute('data-t'), q); });
            });

            // Al enviar: cada producto marcado de la lista actual viaja como incluidos[].
            form.addEventListener('submit', function () {
                var box = document.getElementById('incluidos-inputs'), vigentes = new Set(items.map(function (i) { return i.c.toUpperCase(); }));
                box.innerHTML = '';
                elegidos.forEach(function (c) {
                    if (loaded && !vigentes.has(c)) { return; } // ya no coincide con el filtro: se descarta
                    var h = document.createElement('input'); h.type = 'hidden'; h.name = 'incluidos[]'; h.value = c; box.appendChild(h);
                });
            });

            buscar.addEventListener('input', refresh);
            campo.addEventListener('change', refresh);

            document.getElementById('usuarios-filtro').addEventListener('input', function () {
                var q = this.value.toLowerCase();
                Array.prototype.forEach.call(document.getElementById('usuarios-lista').children, function (l) {
                    l.hidden = q.trim() !== '' && !coincide(l.getAttribute('data-t'), q);
                });
            });
        })();
    </script>

</x-layouts.dashboard>
