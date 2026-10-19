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

                            <div id="preview" class="border rounded p-2 mb-3" aria-live="polite">
                                <div id="preview-resumen" class="mb-2">Calculando coincidencias…</div>
                                <div id="preview-controles" class="d-flex flex-wrap align-items-center mb-2" style="gap: 8px;" hidden>
                                    <input type="search" id="preview-filtro" class="form-control form-control-sm" style="max-width: 240px;" placeholder="Buscar en la lista…" autocomplete="off">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="marcar-todos">Marcar todos</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="marcar-ninguno">Quitar todos</button>
                                </div>
                                <div id="preview-lista" class="small" style="max-height: 280px; overflow-y: auto;"></div>
                                <div class="form-text">Desmarcá los productos a los que esta regla <strong>no</strong> debe aplicar.</div>
                            </div>
                            <div id="excluidos-inputs"></div>

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

                            <div class="mb-3">
                                <label class="form-label" for="usuarios">Usuarios específicos</label>
                                <input type="search" id="usuarios-filtro" class="form-control form-control-sm mb-1" placeholder="Filtrar lista…" autocomplete="off">
                                @php $selUsers = array_map('intval', old('usuarios', $rule->usuarios ?? [])); @endphp
                                <select id="usuarios" name="usuarios[]" class="form-control" multiple size="7">
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}" @selected(in_array($u->id, $selUsers, true))>{{ $u->first_name }} {{ $u->last_name }} — {{ $u->email }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Ctrl/Cmd + clic para varios. Sin elegir ninguno = no se limita por usuario (solo por nivel).</div>
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
                resumen = document.getElementById('preview-resumen'), lista = document.getElementById('preview-lista'),
                controles = document.getElementById('preview-controles'), filtro = document.getElementById('preview-filtro'),
                form = buscar.form, t, items = [], loaded = false,
                excluidos = new Set(@json(array_values(old('excluidos', $rule->excluidos ?? []))));

            function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

            function updateResumen() {
                var ex = items.filter(function (i) { return excluidos.has(i.c.toUpperCase()); }).length;
                resumen.innerHTML = '<strong>' + (items.length - ex) + '</strong> de ' + items.length + ' producto(s) coinciden'
                    + (ex ? ' <span class="text-muted">(' + ex + ' excluido(s))</span>' : '');
            }

            function render() {
                var q = filtro.value.toLowerCase();
                lista.innerHTML = items.map(function (i, n) {
                    var id = 'p' + n, on = !excluidos.has(i.c.toUpperCase());
                    return '<div class="form-check" data-t="' + esc((i.c + ' ' + i.n).toLowerCase()) + '"' + (q && (i.c + ' ' + i.n).toLowerCase().indexOf(q) < 0 ? ' hidden' : '') + '>'
                        + '<input class="form-check-input" type="checkbox" id="' + id + '" data-c="' + esc(i.c.toUpperCase()) + '"' + (on ? ' checked' : '') + '>'
                        + '<label class="form-check-label" for="' + id + '">' + esc(i.c) + (i.n && i.n !== i.c ? ' — ' + esc(i.n) : '') + '</label></div>';
                }).join('');
                controles.hidden = items.length === 0;
                updateResumen();
            }

            function refresh() {
                var url = @json(route('price-rules.preview')) + '?buscar=' + encodeURIComponent(buscar.value) + '&campo=' + campo.value;
                fetch(url, {headers: {Accept: 'application/json'}}).then(function (r) { return r.json(); }).then(function (d) {
                    items = d.items; loaded = true;
                    if (d.total > items.length) { resumen.textContent = 'Demasiados productos (' + d.total + '): afiná la búsqueda para poder elegirlos.'; }
                    render();
                    if (d.total > items.length) { resumen.textContent = 'Se muestran los primeros ' + items.length + ' de ' + d.total + ': afiná la búsqueda para ver el resto.'; }
                }).catch(function () { resumen.textContent = 'No se pudo calcular la vista previa.'; });
            }

            lista.addEventListener('change', function (e) {
                var c = e.target.getAttribute('data-c');
                if (c === null) { return; }
                e.target.checked ? excluidos.delete(c) : excluidos.add(c);
                updateResumen();
            });
            function marcarVisibles(on) {
                Array.prototype.forEach.call(lista.querySelectorAll('.form-check:not([hidden]) input'), function (cb) {
                    cb.checked = on; on ? excluidos.delete(cb.getAttribute('data-c')) : excluidos.add(cb.getAttribute('data-c'));
                });
                updateResumen();
            }
            document.getElementById('marcar-todos').addEventListener('click', function () { marcarVisibles(true); });
            document.getElementById('marcar-ninguno').addEventListener('click', function () { marcarVisibles(false); });
            filtro.addEventListener('input', function () {
                var q = this.value.toLowerCase();
                Array.prototype.forEach.call(lista.children, function (d) { d.hidden = q !== '' && d.getAttribute('data-t').indexOf(q) < 0; });
            });

            // Al enviar: cada producto desmarcado de la lista actual viaja como excluidos[].
            form.addEventListener('submit', function () {
                var box = document.getElementById('excluidos-inputs'), vigentes = new Set(items.map(function (i) { return i.c.toUpperCase(); }));
                box.innerHTML = '';
                excluidos.forEach(function (c) {
                    if (loaded && !vigentes.has(c)) { return; } // ya no coincide con el filtro: se descarta
                    var h = document.createElement('input'); h.type = 'hidden'; h.name = 'excluidos[]'; h.value = c; box.appendChild(h);
                });
            });

            buscar.addEventListener('input', function () { clearTimeout(t); t = setTimeout(refresh, 300); });
            campo.addEventListener('change', refresh);
            refresh();

            document.getElementById('usuarios-filtro').addEventListener('input', function () {
                var q = this.value.toLowerCase();
                Array.prototype.forEach.call(document.getElementById('usuarios').options, function (o) {
                    o.hidden = q !== '' && !o.selected && o.text.toLowerCase().indexOf(q) === -1;
                });
            });
        })();
    </script>

</x-layouts.dashboard>
