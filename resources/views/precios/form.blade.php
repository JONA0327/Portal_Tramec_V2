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
                                            @foreach(\App\Models\PriceRule::TIPOS as $valor => $etiqueta)
                                                <option value="{{ $valor }}" @selected(old('tipo', $rule->tipo) === $valor)>{{ $etiqueta }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3" id="bloque-porcentaje">
                                        <label class="form-label" for="porcentaje">Porcentaje</label>
                                        <div class="input-group">
                                            <input type="number" id="porcentaje" name="porcentaje" value="{{ old('porcentaje', $rule->porcentaje) }}" class="form-control" step="0.01" min="0.01" max="100" required>
                                            <span class="input-group-text">%</span>
                                        </div>
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
                                    <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 8px;">
                                        <input type="search" id="buscar" name="buscar" value="{{ old('buscar', $rule->buscar) }}" class="form-control filtro-regla" style="flex: 1 1 220px; min-width: 0;" maxlength="255"
                                            placeholder="Buscar productos: 25HP, C2000, DELTA… (vacío = todos)" autocomplete="off" aria-label="Buscar productos a los que aplica la regla">
                                        <select id="campo" name="campo" class="form-control" style="flex: 0 1 190px;" aria-label="Buscar en">
                                            <option value="ambos" @selected(old('campo', $rule->campo) === 'ambos')>Código o descripción</option>
                                            <option value="codigo" @selected(old('campo', $rule->campo) === 'codigo')>Solo código</option>
                                            <option value="nombre" @selected(old('campo', $rule->campo) === 'nombre')>Solo descripción</option>
                                        </select>
                                    </div>
                                    <div id="preview-controles" class="d-flex flex-wrap align-items-center mb-2" style="gap: 8px;" hidden>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="marcar-todos">Marcar todos</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="marcar-ninguno">Quitar todos</button>
                                        <span class="form-text mb-0">Varios términos separados por coma.</span>
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

                            <div class="mb-3">
                                    <label class="form-label" for="excepciones-filtro">Excepciones: usuarios que NO reciben esta regla (con precio temporal ven el original y el nuevo en verde)</label>
                                    <input type="search" id="excepciones-filtro" class="form-control form-control-sm mb-1" placeholder="Filtrar lista…" autocomplete="off">
                                    @php $selExc = array_map('intval', old('usuarios_excluidos', $rule->usuarios_excluidos ?? [])); @endphp
                                    <div id="excepciones-lista" class="lista-casillas border rounded" style="max-height: 220px; overflow-y: auto;">
                                        @foreach($users as $u)
                                            <label data-t="{{ mb_strtolower($u->first_name.' '.$u->last_name.' '.$u->email) }}">
                                                <input type="checkbox" name="usuarios_excluidos[]" value="{{ $u->id }}" @checked(in_array($u->id, $selExc, true))>
                                                <span>{{ $u->first_name }} {{ $u->last_name }} — {{ $u->email }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="form-text">
                                        Con "Ocultar precio": marca aquí a quienes <strong>sí</strong> verán el precio real. En un descuento o
                                        aumento, quienes marques no lo reciben.
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
                controles = document.getElementById('preview-controles'),
                form = buscar.form, todos = null, items = [], loaded = false, truncado = '',
                elegidos = new Set(@json(array_values(old('incluidos', $rule->incluidos ?? []))));

            var MAX = 200, visibles = MAX; // se dibujan solo las primeras filas (con "Mostrar más"): 1,200 casillas por letra eran lentas
            var ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' };
            function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return ESC[c]; }); }

            function updateResumen() {
                var n = 0; for (var k = 0; k < items.length; k++) { if (elegidos.has(items[k].cu)) { n++; } }
                resumen.innerHTML = (n
                    ? '<strong>' + n + '</strong> de ' + items.length + ' producto(s) elegido(s): la regla aplica solo a ellos'
                    : 'Sin selección: la regla aplica a los <strong>' + items.length + '</strong> producto(s) que coinciden')
                    + truncado;
            }

            function render() {
                var html = [], n = Math.min(items.length, visibles);
                for (var k = 0; k < n; k++) {
                    var i = items[k];
                    html.push('<label><input type="checkbox" data-c="' + esc(i.cu) + '"' + (elegidos.has(i.cu) ? ' checked' : '') + '>'
                        + '<span>' + esc(i.c) + (i.n && i.n !== i.c ? ' — ' + esc(i.n) : '') + '</span></label>');
                }
                if (items.length > n) {
                    html.push('<button type="button" class="btn btn-sm btn-outline-secondary m-2" id="mostrar-mas">Mostrar más (' + (items.length - n) + ' restantes)</button>');
                }
                lista.innerHTML = html.join('');
                controles.hidden = items.length === 0;
                updateResumen();
            }

            // Misma regla que App\Support\Search: cada palabra debe aparecer, en cualquier orden.
            function coincide(texto, q) {
                texto = texto.toLowerCase();
                return q.toLowerCase().split(/\s+/).filter(Boolean).every(function (w) { return texto.indexOf(w) >= 0; });
            }

            // Misma regla que PriceRule::matchesFilter: términos separados por coma (cualquiera), cada término = todas sus
            // palabras, en cualquier orden; vacío = todos. Los textos en minúsculas se calculan una vez al cargar.
            function refresh() {
                if (!todos) { return; }
                var terms = buscar.value.split(',').map(function (x) { return x.toLowerCase().split(/\s+/).filter(Boolean); }).filter(function (w) { return w.length; });
                var donde = campo.value;
                visibles = MAX;
                items = !terms.length ? todos : todos.filter(function (i) {
                    var texto = donde === 'codigo' ? i.tc : donde === 'nombre' ? i.tn : i.t;
                    return terms.some(function (words) { return words.every(function (w) { return texto.indexOf(w) >= 0; }); });
                });
                render();
            }

            // El catálogo viene incrustado en la página (ver PriceRulesController::previewItems): sin segunda petición.
            (function (d) {
                todos = d.items.map(function (i) {
                    var c = (i.c || '').toLowerCase(), n = (i.n || '').toLowerCase();
                    i.cu = (i.c || '').toUpperCase(); i.tc = c; i.tn = n; i.t = c + ' ' + n;
                    return i;
                });
                loaded = true;
                truncado = d.total > todos.length ? ' <span class="text-muted">(catálogo recortado a ' + todos.length + ' de ' + d.total + ')</span>' : '';
                if (!todos.length) { resumen.textContent = 'El catálogo de productos está vacío en este momento (se sincroniza cada 2 minutos); recargá la página en un momento.'; return; }
                refresh();
            })(@json($catalogo));

            lista.addEventListener('change', function (e) {
                var c = e.target.getAttribute('data-c');
                if (c === null) { return; }
                e.target.checked ? elegidos.add(c) : elegidos.delete(c);
                updateResumen();
            });
            function marcarTodos(on) {
                items.forEach(function (i) { on ? elegidos.add(i.cu) : elegidos.delete(i.cu); });
                render();
            }
            document.getElementById('marcar-todos').addEventListener('click', function () { marcarTodos(true); });
            document.getElementById('marcar-ninguno').addEventListener('click', function () { marcarTodos(false); });
            lista.addEventListener('click', function (e) {
                if (e.target.id === 'mostrar-mas') { visibles += MAX; render(); }
            });

            // Al enviar: cada producto marcado de la lista actual viaja como incluidos[].
            form.addEventListener('submit', function () {
                var box = document.getElementById('incluidos-inputs'), vigentes = new Set(items.map(function (i) { return i.cu; }));
                box.innerHTML = '';
                elegidos.forEach(function (c) {
                    if (loaded && !vigentes.has(c)) { return; } // ya no coincide con el filtro: se descarta
                    var h = document.createElement('input'); h.type = 'hidden'; h.name = 'incluidos[]'; h.value = c; box.appendChild(h);
                });
            });

            var pausa = null;
            buscar.addEventListener('input', function () { clearTimeout(pausa); pausa = setTimeout(refresh, 120); });
            campo.addEventListener('change', refresh);

            // "Mostrar en $0.00" no usa porcentaje
            var tipo = document.getElementById('tipo'), bloquePct = document.getElementById('bloque-porcentaje'), pct = document.getElementById('porcentaje');
            function alternarPorcentaje() {
                var cero = tipo.value === 'cero' || tipo.value === 'temporal'; // sin porcentaje
                bloquePct.hidden = cero;
                pct.required = !cero;
            }
            tipo.addEventListener('change', alternarPorcentaje);
            alternarPorcentaje();

            document.getElementById('excepciones-filtro').addEventListener('input', function () {
                var q = this.value.toLowerCase();
                Array.prototype.forEach.call(document.getElementById('excepciones-lista').children, function (l) {
                    l.hidden = q.trim() !== '' && !coincide(l.getAttribute('data-t'), q);
                });
            });

            document.getElementById('usuarios-filtro').addEventListener('input', function () {
                var q = this.value.toLowerCase();
                Array.prototype.forEach.call(document.getElementById('usuarios-lista').children, function (l) {
                    l.hidden = q.trim() !== '' && !coincide(l.getAttribute('data-t'), q);
                });
            });
        })();
    </script>

</x-layouts.dashboard>
