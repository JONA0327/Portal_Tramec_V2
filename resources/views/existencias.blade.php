{{-- Existencias: cantidad por producto+almacén, en vivo desde StockLevelService
     (caché + sync completo cada 15 min + webhook en tiempo real desde
     servicio-stock::_vigilar_existencias). Las cantidades no se editan acá --
     son 100% información del ERP -- pero sí se ven los precios por canal (tabla
     local `products`), de solo lectura también, con copiar-al-portapapeles
     igual que en v1 (ver portal-tramec/public/dash-assets/js/copy-price.js). --}}
<x-layouts.dashboard>

    @php
        // Columnas de precio según el nivel del usuario (ver User::columnasPrecio()).
        $columnasPrecio = auth()->user()->columnasPrecio();
        $puedeCopiar = auth()->user()->puedeCopiarPrecios();
        $esAdmin = auth()->user()->isAdmin();
        $puedeCotizar = auth()->user()->puedeCotizar(); // administrador + equipo Tramec: carrito de cotización
    @endphp

    <x-slot name="estado">
        @if($lastSyncAt)
        Actualizado: {{ \Illuminate\Support\Carbon::parse($lastSyncAt)->locale('es')->diffForHumans() }}
        @else
        Todavía sin sincronizar
        @endif
        @if($esAdmin && $counts['ocultos'] > 0)
        &nbsp;·&nbsp;<a href="{{ route('existencias', ['estado' => 'ocultos']) }}" class="text-muted">{{ $counts['ocultos'] }} ocultos</a>
        @endif
        @if($esAdmin && $counts['bajas'] > 0)
        &nbsp;·&nbsp;<a href="{{ route('bajas') }}" class="text-muted">{{ $counts['bajas'] }} en bajas</a>
        @endif
        &nbsp;·&nbsp;<span id="live-status" class="text-muted"
        @if(! config('broadcasting.connections.pusher.key')) title="Falta configurar PUSHER_APP_KEY / PUSHER_APP_CLUSTER y BROADCAST_CONNECTION=pusher en el .env del servidor" @endif>
        {{ config('broadcasting.connections.pusher.key') ? '○ Conectando…' : '○ Tiempo real desactivado' }}
        </span>
    </x-slot>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <x-stock-status :last-sync="$lastSyncAt" />
                        @if(session('status'))
                            <div class="alert alert-success">{{ session('status') }}</div>
                        @endif

                        @error('archivo')<div class="alert alert-danger">{{ $message }}</div>@enderror

                            {{-- Avanzado: empresa + almacén + estado + palabras (en cualquier orden); busca solo al
                                 pulsar "Buscar" o Enter. "Agrupar por modelo" junta los almacenes de cada modelo en un renglón. --}}
                            <form method="GET" action="{{ route('existencias') }}" class="filtros-barra">
                                <input type="hidden" name="filtros" value="1">{{-- distingue "filtros enviados" de "entrada sin filtros" (ver preferenciaAgrupar) --}}
                                <input type="hidden" name="por_pagina" value="{{ $perPage }}">
                                <select name="empresa" class="form-control" aria-label="Empresa">
                                    <option value="">Todas las empresas</option>
                                    @foreach($empresas as $empresaOption)
                                        <option value="{{ $empresaOption }}" @selected($empresa === $empresaOption)>{{ $empresaOption }}</option>
                                    @endforeach
                                </select>
                                <select name="almacen" class="form-control" aria-label="Almacén">
                                    <option value="">Todos los almacenes</option>
                                    @foreach($almacenes as $almacenOption)
                                        <option value="{{ $almacenOption }}" @selected($almacen === $almacenOption)>{{ $almacenOption }}</option>
                                    @endforeach
                                </select>
                                <select name="clasificacion" class="form-control" aria-label="Clasificación">
                                    <option value="">Todas las clasificaciones</option>
                                    @foreach($clasificaciones as $c)
                                        <option value="{{ $c }}" @selected($clasificacion === $c)>{{ $c }}</option>
                                    @endforeach
                                    <option value="__sin" @selected($clasificacion === '__sin')>Sin clasificación</option>
                                </select>
                                <div class="grupo-busqueda">
                                    <input type="text" name="buscar" id="buscar-input" value="{{ $buscar }}" class="form-control filtro-buscar"
                                        placeholder="Buscar por código o descripción..." autocomplete="off">
                                    <button type="submit" class="btn btn-primary">Buscar</button>
                                    <a href="{{ route('existencias') }}" class="btn btn-limpiar">Limpiar</a>
                                </div>
                                @can('admin')
                                    <button type="button" class="btn btn-secondary ml-auto" data-toggle="modal" data-target="#modalExcel">Cargar Excel</button>
                                @endcan
                                <div class="filtros-opciones">
                                    <label class="filtro-check">
                                        <input type="checkbox" name="agrupar" value="1" @checked($agrupar)>
                                        Agrupar por modelo
                                    </label>
                                    @if($esAdmin)
                                        {{-- Solo el administrador (nivel 0): las filas ocultas a mano se ven aparte, con esta casilla. --}}
                                        <label class="filtro-check">
                                            <input type="checkbox" name="estado" value="ocultos" @checked($estado === 'ocultos')>
                                            Ver ocultos
                                        </label>
                                    @endif
                                </div>
                            </form>

                            @can('admin')
                            {{-- Carga del Excel de Schenker en un modal (Bootstrap 4 del tema): al elegir el archivo
                                 se pasa a la pantalla de revisión antes de aplicar nada. --}}
                            <div class="modal fade" id="modalExcel" tabindex="-1" role="dialog" aria-labelledby="modalExcelTitulo" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <form method="POST" action="{{ route('existencias.importar') }}" enctype="multipart/form-data">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="modalExcelTitulo">Cargar Excel de Schenker</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="text-muted mb-3">
                                                    Archivo <strong>.xlsx</strong> con las columnas <strong>Referencia</strong> y <strong>Disp</strong>.
                                                    Es la foto completa del almacén DELTA Schenker: lo que ya no venga en el archivo se quita.
                                                    Antes de aplicar nada verás un resumen para confirmar.
                                                </p>
                                                <label class="form-label" for="archivo-excel">Archivo Excel</label>
                                                <input type="file" id="archivo-excel" name="archivo" accept=".xlsx" class="form-control" required>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-primary">Revisar archivo</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endcan

                        <div id="resultados">
                        <x-por-pagina :actual="$perPage" />

                        @if($levels->isEmpty())
                            <p class="mb-0">
                                @if(!$lastSyncAt)
                                    Todavía no se sincronizaron las existencias con el servicio de existencias.
                                    Corré <code>php artisan stock-levels:sync</code> (o esperá al programador de tareas).
                                @else
                                    No se encontraron existencias con esos filtros.
                                @endif
                            </p>
                        @else
                            @if($puedeCotizar)
                                <form id="form-masivo" method="POST" action="{{ route('cotizador.agregar') }}">@csrf</form>
                                <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 8px;">
                                    <span class="text-muted">Seleccionados: <strong id="sel-contador">0</strong></span>
                                    <button type="submit" form="form-masivo" class="btn btn-sm btn-primary" data-carrito disabled
                                        title="Agrega lo marcado al carrito y ábrelo en Cotizador">Agregar al carrito de cotización</button>
                                </div>
                            @endif
                            @if($agrupar)
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            @if($puedeCotizar)<th style="width: 34px;"><input type="checkbox" class="sel-todos" aria-label="Seleccionar todos los de esta página"></th>@endif
                                            <th>Código</th>
                                            <th>Descripción</th>
                                            <th>Empresa</th>
                                            @foreach($columnasPrecio as $label)
                                                <th>{{ $label }}</th>
                                            @endforeach
                                            <th>Existencia por almacén</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($levels as $row)
                                            <tr>
                                                @if($puedeCotizar)
                                                    @php $alm0 = collect($row['almacenes'])->firstWhere(fn ($x) => $x['cantidad'] > 0) ?? $row['almacenes'][0]; @endphp
                                                    <td><input type="checkbox" class="sel-item" form="form-masivo" name="items[]" value="ex:{{ $row['empresa'] }}:{{ $alm0['id_producto'] }}:{{ $alm0['id_almacen'] }}" aria-label="Seleccionar {{ $row['codigo'] }}"></td>
                                                @endif
                                                <td class="col-codigo">{{ $row['codigo'] }}</td>
                                                <td class="col-nombre">{{ $row['descripcion'] }}</td>
                                                <td>{{ $row['empresa'] }}</td>
                                                @foreach($columnasPrecio as $key => $label)
                                                    <td class="text-nowrap {{ $row[$key] !== null && $puedeCopiar ? 'copy-price-cell' : '' }} {{ isset($row['originales'][$key]) ? 'precio-ajustado' : '' }}"
                                                        @isset($row['originales'][$key]) title="Precio original: {{ number_format($row['originales'][$key], 2, '.', ',') }} (ajustado por regla)" @endisset
                                                        data-modelo="{{ $row['codigo'] }}" data-descripcion="{{ $row['descripcion'] }}"
                                                        data-existencia="Existencia total: {{ rtrim(rtrim(number_format($row['total'], 2, '.', ''), '0'), '.') ?: '0' }} ({{ collect($row['almacenes'])->map(fn ($a) => $a['almacen'].': '.(rtrim(rtrim(number_format($a['cantidad'], 2, '.', ''), '0'), '.') ?: '0'))->implode(', ') }})">
                                                        @if($row[$key] !== null)
                                                            {{ number_format($row[$key], 2, '.', ',') }}&nbsp;{{ $row['moneda'] }}
                                                            @if($puedeCopiar)
                                                            <button type="button" class="copy-price-btn" title="Copiar Código, Descripción y {{ $label }}">
                                                                <i class="fas fa-copy" aria-hidden="true"></i>
                                                            </button>
                                                            @endif
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                @endforeach
                                                <td>
                                                    @foreach($row['almacenes'] as $alm)
                                                        <{{ $esAdmin ? 'a' : 'span' }} @if($esAdmin) href="{{ route('existencias.edit', [$alm['empresa'], $alm['id_producto'], $alm['id_almacen']]) }}" @endif
                                                            class="d-inline-block border rounded px-2 py-1 mr-1 mb-1 text-nowrap text-decoration-none {{ $alm['de_baja'] ? 'text-muted' : '' }}"
                                                            @if($esAdmin) title="Opciones de {{ $alm['almacen'] }}" @endif>
                                                            {{ $alm['almacen'] }}:
                                                            <strong>{{ rtrim(rtrim(number_format($alm['cantidad'], 2, '.', ','), '0'), '.') ?: '0' }}</strong>
                                                        </{{ $esAdmin ? 'a' : 'span' }}>
                                                    @endforeach
                                                </td>
                                                <td class="text-nowrap"><strong>{{ rtrim(rtrim(number_format($row['total'], 2, '.', ','), '0'), '.') ?: '0' }}</strong></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            @if($puedeCotizar)<th style="width: 34px;"><input type="checkbox" class="sel-todos" aria-label="Seleccionar todos los de esta página"></th>@endif
                                            <th>Código</th>
                                            <th>Descripción</th>
                                            <th>Empresa</th>
                                            <th>Almacén</th>
                                            <th>Cantidad</th>
                                            @foreach($columnasPrecio as $label)
                                                <th>{{ $label }}</th>
                                            @endforeach
                                            @if($esAdmin)<th>Acciones</th>@endif
                                        </tr>
                                    </thead>
                                    <tbody id="existencias-tbody">
                                        @foreach($levels as $row)
                                            <tr data-level-id="{{ $row['empresa'].':'.$row['id_producto'].':'.$row['id_almacen'] }}">
                                                @if($puedeCotizar)
                                                    <td><input type="checkbox" class="sel-item" form="form-masivo" name="items[]" value="ex:{{ $row['empresa'] }}:{{ $row['id_producto'] }}:{{ $row['id_almacen'] }}" aria-label="Seleccionar {{ $row['codigo'] }} en {{ $row['almacen'] }}"></td>
                                                @endif
                                                <td class="col-codigo">{{ $row['codigo'] }}</td>
                                                <td class="col-nombre">{{ $row['descripcion'] }}</td>
                                                <td>{{ $row['empresa'] }}</td>
                                                <td class="col-almacen">{{ $row['almacen'] }}</td>
                                                <td class="col-cantidad">
                                                    {{ rtrim(rtrim(number_format($row['cantidad'], 2, '.', ','), '0'), '.') ?: '0' }}
                                                </td>
                                                @foreach($columnasPrecio as $key => $label)
                                                    <td class="text-nowrap {{ $row[$key] !== null && $puedeCopiar ? 'copy-price-cell' : '' }} {{ isset($row['originales'][$key]) ? 'precio-ajustado' : '' }}"
                                                        @isset($row['originales'][$key]) title="Precio original: {{ number_format($row['originales'][$key], 2, '.', ',') }} (ajustado por regla)" @endisset
                                                        data-modelo="{{ $row['codigo'] }}" data-descripcion="{{ $row['descripcion'] }}">
                                                        @if($row[$key] !== null)
                                                            {{ number_format($row[$key], 2, '.', ',') }}&nbsp;{{ $row['moneda'] }}
                                                            @if($puedeCopiar)
                                                            <button type="button" class="copy-price-btn" title="Copiar Código, Descripción y {{ $label }}">
                                                                <i class="fas fa-copy" aria-hidden="true"></i>
                                                            </button>
                                                            @endif
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                @endforeach
                                                @if($esAdmin)
                                                <td class="text-nowrap">
                                                    @php
                                                        $rowUrlBase = route('existencias.edit', [$row['empresa'], $row['id_producto'], $row['id_almacen']]);
                                                    @endphp
                                                    <a href="{{ $rowUrlBase }}" class="btn btn-sm btn-primary">Opciones</a>
                                                    @if(true)
                                                        <a href="{{ route('existencias.confirm-baja', [$row['empresa'], $row['id_producto'], $row['id_almacen']]) }}" class="btn btn-sm btn-danger">Dar de baja</a>
                                                    @endif
                                                </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @endif

                            <div class="mt-4">
                                {{ $levels->links('pagination::bootstrap-5') }}
                            </div>
                        @endif
                        </div>{{-- #resultados --}}
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('partials.copy-price')
    @can('admin')
    <script>
        // El modal se mueve al <body> para que no lo recorten los contenedores de la tarjeta (jQuery carga más abajo).
        document.addEventListener('DOMContentLoaded', function () {
            $('#modalExcel').appendTo('body');
        });
    </script>
    @endcan

    @if(config('broadcasting.connections.pusher.key'))
        {{-- Actualiza en vivo la fila visible cuando servicio-stock detecta un
             cambio real de cantidad (ver App\Events\ExistenciaChanged). Si la
             fila cambiada no está en la página actual, no hace nada. --}}
        <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
        <script>
            (function () {
                var ocultarSinExistencia = @json($estado === 'con_existencia'); // en la lista de ocultos sí se ven en cero
                var estado = document.getElementById('live-status');
                function enVivo(ok, texto) {
                    if (estado) { estado.textContent = (ok ? '● ' : '○ ') + texto; estado.className = ok ? 'text-success' : 'text-muted'; }
                }
                if (typeof Pusher === 'undefined') {
                    enVivo(false, 'Sin tiempo real (no cargó pusher-js)');
                    return;
                }
                var pusher = new Pusher(@json(config('broadcasting.connections.pusher.key')), {
                    cluster: @json(config('broadcasting.connections.pusher.options.cluster')),
                });
                pusher.connection.bind('state_change', function (s) {
                    enVivo(s.current === 'connected', s.current === 'connected' ? 'En vivo' : (s.current === 'connecting' ? 'Conectando…' : 'Sin conexión en vivo'));
                });
                pusher.connection.bind('error', function () { enVivo(false, 'Sin conexión en vivo'); });

                pusher.subscribe('existencias-updates').bind('existencia.changed', function (level) {
                    var key = (level.empresa_codigo || 'TRAMEC') + ':' + level.id_producto + ':' + level.id_almacen;
                    var row = document.querySelector('#existencias-tbody tr[data-level-id="' + key + '"]');
                    if (!row) {
                        return;
                    }
                    var cantidad = Number(level.cantidad ?? 0);
                    // Sin existencia deja de mostrarse (reaparece al recargar cuando vuelva a haber).
                    if (cantidad <= 0 && ocultarSinExistencia) {
                        row.classList.add('table-warning');
                        setTimeout(function () { row.remove(); }, 1200);
                        return;
                    }
                    row.querySelector('.col-cantidad').textContent = cantidad % 1 === 0 ? cantidad.toFixed(0) : cantidad.toFixed(2);
                    row.classList.add('table-warning');
                    setTimeout(function () { row.classList.remove('table-warning'); }, 2000);
                });
            })();
        </script>
    @endif

    <script src="{{ asset('dash-assets/js/busqueda-en-vivo.js') }}" defer></script>
    @if($puedeCotizar)
        <script src="{{ asset('dash-assets/js/seleccion-masiva.js') }}" defer></script>
    @endif

</x-layouts.dashboard>
