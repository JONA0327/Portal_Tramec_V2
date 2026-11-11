{{-- Catálogo unificado: cada fila del ERP (StockService, ambas empresas) cruzada
     con la tabla local de precios `products` por idERP/idERP_empresa (ver
     ProductsController). El nombre/código de una fila con precio local lo
     mantiene al día el ERP automáticamente (App\Listeners\SyncLocalProductFromErp);
     acá nunca se editan a mano. v1 (admin/products) además traía datatable con
     import/export; eso todavía no se porta. --}}
<x-layouts.dashboard>

    @php
        // Columnas de precio según el nivel del usuario (ver User::columnasPrecio()); acciones solo administrador.
        $columnasPrecio = auth()->user()->columnasPrecio();
        $esAdmin = auth()->user()->isAdmin();
        $puedeCotizar = auth()->user()->puedeCotizar(); // administrador + equipo Tramec: carrito de cotización
        $seleccion = $esAdmin || $puedeCotizar;
        $puedeCopiar = auth()->user()->puedeCopiarPrecios();
        $verId = in_array((int) auth()->user()->level, [0, 1, 7], true); // v1: el ID del ERP solo para los niveles internos
    @endphp

    <x-slot name="estado">
        @if($lastSyncAt)
        Catálogo actualizado: {{ \Illuminate\Support\Carbon::parse($lastSyncAt)->locale('es')->diffForHumans() }} ({{ \App\Support\SyncLog::mx($lastSyncAt)->format('d/m/Y H:i') }} hora de México)
        @else
        Catálogo aún sin sincronizar
        @endif
        @if(! $cliente && $counts['nuevo'] > 0)
        &nbsp;·&nbsp;<a href="{{ route('products', ['estado' => 'nuevo']) }}" class="text-info">🆕 {{ $counts['nuevo'] }} nuevos</a>
        @endif
        @if(! $cliente && $counts['advertencia'] > 0)
        &nbsp;·&nbsp;<a href="{{ route('products', ['estado' => 'advertencia']) }}" class="text-warning">⚠ {{ $counts['advertencia'] }} sin emparejar</a>
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
                        @if(session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif

                            {{-- Avanzado: empresa + estado + palabras (en cualquier orden); busca solo al
                                 pulsar "Buscar" o Enter. --}}
                            <form method="GET" action="{{ route('products') }}" class="filtros-barra">
                                <input type="hidden" name="por_pagina" value="{{ $perPage }}">
                                <button type="button" class="btn btn-secondary filtros-toggle" aria-expanded="false" aria-controls="filtros-extra">Filtros</button>
                                <div class="filtros-extra" id="filtros-extra">
                                    <select name="empresa" class="form-control" aria-label="Empresa">
                                        <option value="">Todas las empresas</option>
                                        @foreach($empresas as $empresaOption)
                                            <option value="{{ $empresaOption }}" @selected($empresa === $empresaOption)>{{ $empresaOption }}</option>
                                        @endforeach
                                    </select>
                                    @unless($cliente)
                                    <select name="estado" class="form-control" aria-label="Estado">
                                        <option value="" @selected($estado === '')>Todos los estados</option>
                                        <option value="normal" @selected($estado === 'normal')>Solo validados</option>
                                        <option value="nuevo" @selected($estado === 'nuevo')>🆕 Solo nuevos</option>
                                        <option value="advertencia" @selected($estado === 'advertencia')>⚠ Solo sin emparejar</option>
                                    </select>
                                    @endunless
                                    <select name="clasificacion" class="form-control" aria-label="Clasificación">
                                        <option value="">Todas las clasificaciones</option>
                                        @foreach($clasificaciones as $c)
                                            <option value="{{ $c }}" @selected($clasificacion === $c)>{{ $c }}</option>
                                        @endforeach
                                        <option value="__sin" @selected($clasificacion === '__sin')>Sin clasificación</option>
                                    </select>
                                </div>
                                <div class="grupo-busqueda">
                                    <input type="text" name="buscar" id="buscar-input" value="{{ $buscar }}" class="form-control filtro-buscar"
                                        placeholder="Buscar por código o descripción..." autocomplete="off">
                                    <button type="submit" class="btn btn-primary">Buscar</button>
                                    <a href="{{ route('products') }}" class="btn btn-limpiar">Limpiar</a>
                                </div>
                            </form>

                        <div id="resultados">
                        <x-por-pagina :actual="$perPage" />

                        @if($products->isEmpty())
                            <p class="mb-0">
                                @if(!$lastSyncAt)
                                    Todavía no se sincronizó el catálogo con el servicio de existencias.
                                    Corré <code>php artisan stock:sync</code> (o esperá a que corra el programador de tareas).
                                @else
                                    No se encontraron productos con esos filtros.
                                @endif
                            </p>
                        @else
                            @if($seleccion)
                                {{-- Selección de varios: carrito de cotización (administrador y equipo Tramec) y, solo administrador, registro y
                                     baja masivos. Un solo formulario (las casillas de cada fila lo usan con form="form-masivo", no se puede anidar);
                                     el envío normal agrega al carrito y los botones de administrador cambian la acción con formaction. --}}
                                <form id="form-masivo" method="POST" action="{{ route('cotizador.agregar') }}">@csrf</form>
                                <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 8px;">
                                    <span class="text-muted">Seleccionados: <strong id="sel-contador">0</strong></span>
                                    <button type="button" class="btn btn-sm btn-limpiar" data-sel-limpiar hidden>Quitar selección</button>
                                    <button type="submit" form="form-masivo" class="btn btn-sm btn-primary" data-carrito disabled
                                        title="Agrega lo marcado al carrito y ábrelo en Cotizador">Agregar al carrito de cotización</button>
                                    @if($esAdmin)
                                        <button type="submit" form="form-masivo" formaction="{{ route('products.masivo') }}" name="accion" value="registrar" class="btn btn-sm btn-info" data-masivo="registrar" disabled>Registrar seleccionados</button>
                                        <button type="submit" form="form-masivo" formaction="{{ route('products.masivo') }}" name="accion" value="baja" class="btn btn-sm btn-danger" data-masivo="baja" disabled
                                            data-confirm="¿Dar de baja los {n} producto(s) seleccionados? Salen de Productos y de Existencias y, aunque CONTPAQi los siga enviando, el portal los ignora. Puedes reactivarlos desde Bajas.">Dar de baja seleccionados</button>
                                    @endif
                                </div>
                            @endif
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            @if($seleccion)<th style="width: 34px;"><input type="checkbox" class="sel-todos" aria-label="Seleccionar todos los de esta página"></th>@endif
                                            @if($verId)<th>ID</th>@endif
                                            <th>Código</th>
                                            <th>Descripción</th>
                                            <th>Empresa</th>
                                            @foreach($columnasPrecio as $label)
                                                <th>{{ $label }}</th>
                                            @endforeach
                                            @if($esAdmin)<th>Acciones</th>@endif
                                        </tr>
                                    </thead>
                                    <tbody id="products-tbody">
                                        @foreach($products as $row)
                                            {{-- TRAMEC y OMEC tienen cada una su propio id: el mismo número en las
                                                 dos bases son productos distintos, por eso la clave para el update
                                                 en vivo (Pusher) va con la empresa. Las filas "advertencia" no
                                                 tienen empresa del ERP (no se encontró match), no reciben updates en vivo. --}}
                                            <tr @if($row['erp_empresa']) data-product-id="{{ $row['erp_empresa'].':'.$row['erp_id'] }}" @endif>
                                                @if($seleccion)
                                                    <td><input type="checkbox" class="sel-item" form="form-masivo" name="items[]"
                                                        value="{{ $row['erp_empresa'] ? 'erp:'.$row['erp_empresa'].':'.$row['erp_id'] : 'loc:'.$row['local_id'] }}"
                                                        data-estado="{{ $row['estado'] }}" aria-label="Seleccionar {{ $row['codigo'] }}"></td>
                                                @endif
                                                @if($verId)
                                                <td class="col-id">
                                                    @if($row['estado'] === 'nuevo')
                                                        <span class="rounded-circle d-inline-block bg-info" style="width: 8px; height: 8px;" title="Existe en el ERP, todavía sin precio cargado"></span>
                                                    @elseif($row['estado'] === 'advertencia')
                                                        <span class="rounded-circle d-inline-block bg-warning" style="width: 8px; height: 8px;" title="No se encontró este producto en el catálogo actual del ERP"></span>
                                                    @else
                                                        <span class="rounded-circle d-inline-block bg-success" style="width: 8px; height: 8px;" title="Emparejado con el ERP"></span>
                                                    @endif
                                                    {{ $row['erp_id'] ?? '-' }}
                                                </td>
                                                @endif
                                                <td class="col-codigo">{{ $row['codigo'] }}</td>
                                                <td class="col-nombre">{{ $row['descripcion'] }}</td>
                                                <td>{{ $row['empresa'] ?? '-' }}</td>
                                                @foreach($columnasPrecio as $key => $label)
                                                    <td class="text-nowrap {{ $row['precios'][$key] !== null && $puedeCopiar ? 'copy-price-cell' : '' }} {{ isset($row['originales'][$key]) ? 'precio-ajustado' : '' }}"
                                                        @isset($row['originales'][$key]) title="Precio original: {{ number_format($row['originales'][$key], 2, '.', ',') }} (ajustado por regla)" @endisset
                                                        data-modelo="{{ $row['codigo'] }}" data-descripcion="{{ $row['descripcion'] }}">
                                                        @if($row['precios'][$key] !== null)
                                                            {{ number_format($row['precios'][$key], 2, '.', ',') }}&nbsp;{{ $row['moneda'] }}
                                                            @isset($row['temporales'][$key])
                                                                <br><span class="precio-temporal" style="color: green;">{{ number_format($row['temporales'][$key], 2, '.', ',') }}&nbsp;{{ $row['moneda'] }}</span>
                                                            @endisset
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
                                                    @if($row['local_id'])
                                                        <a href="{{ route('products.show', $row['local_id']) }}" class="btn btn-sm btn-secondary">Ver</a>
                                                        <a href="{{ route('products.edit', $row['local_id']) }}" class="btn btn-sm btn-primary">Editar</a>
                                                        <form method="POST" action="{{ route('bajas.store') }}" class="d-inline"
                                                        onsubmit="return confirm('¿Dar de baja este producto? Sale de Productos y de Existencias (todos los almacenes) y, aunque CONTPAQi lo siga enviando, el portal lo ignora. Puedes reactivarlo desde Bajas.')">
                                                        @csrf
                                                        <input type="hidden" name="empresa" value="{{ $row['erp_empresa'] }}">
                                                        <input type="hidden" name="codigo" value="{{ $row['codigo'] }}">
                                                        <input type="hidden" name="id_erp" value="{{ $row['erp_id'] }}">
                                                        <button type="submit" class="btn btn-sm btn-danger">Dar de baja</button>
                                                    </form>
                                                    @else
                                                        {{-- Registro de un clic: crea el registro local ya vinculado al
                                                             idERP (nombre/código los toma del ERP), sin pedir nada más.
                                                             El precio se carga después con "Editar". --}}
                                                        <form method="POST" action="{{ route('products.register') }}" class="d-inline">
                                                            @csrf
                                                            <input type="hidden" name="erp_empresa" value="{{ $row['erp_empresa'] }}">
                                                            <input type="hidden" name="erp_id" value="{{ $row['erp_id'] }}">
                                                            <button type="submit" class="btn btn-sm btn-info">Registrar</button>
                                                        </form>
                                                        {{-- Para servicios/personalizaciones de única ocasión que no son
                                                             catálogo real (ver servicio-stock: /api/blacklist/{empresa}).
                                                             Reversible: no borra nada, solo lo oculta. --}}
                                                        <form method="POST" action="{{ route('products.block') }}" class="d-inline"
                                                            onsubmit="return confirm('¿Bloquear este producto? Va a dejar de aparecer en el catálogo (podés desbloquearlo después desde servicio-stock).');">
                                                            @csrf
                                                            <input type="hidden" name="erp_empresa" value="{{ $row['erp_empresa'] }}">
                                                            <input type="hidden" name="erp_id" value="{{ $row['erp_id'] }}">
                                                            <button type="submit" class="btn btn-sm btn-bloquear">Bloquear</button>
                                                        </form>
                                                        <form method="POST" action="{{ route('bajas.store') }}" class="d-inline"
                                                        onsubmit="return confirm('¿Dar de baja este producto? Sale de Productos y de Existencias (todos los almacenes) y, aunque CONTPAQi lo siga enviando, el portal lo ignora. Puedes reactivarlo desde Bajas.')">
                                                        @csrf
                                                        <input type="hidden" name="empresa" value="{{ $row['erp_empresa'] }}">
                                                        <input type="hidden" name="codigo" value="{{ $row['codigo'] }}">
                                                        <input type="hidden" name="id_erp" value="{{ $row['erp_id'] }}">
                                                        <button type="submit" class="btn btn-sm btn-danger">Dar de baja</button>
                                                    </form>
                                                    @endif
                                                </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4">
                                {{ $products->links('pagination::bootstrap-5') }}
                            </div>
                        @endif
                        </div>{{-- #resultados --}}
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(config('broadcasting.connections.pusher.key'))
        {{-- Actualiza en vivo la fila de un producto si justo está visible en esta página,
             cuando StockService detecta un cambio real (ver App\Events\ProductChanged). Si
             el producto cambiado no está en la página actual, no hace nada (no reordena ni
             pagina sola: eso sería más confuso que útil mientras alguien está mirando la tabla). --}}
        <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
        <script>
            (function () {
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

                pusher.subscribe('stock-updates').bind('product.changed', function (product) {
                    var key = (product.empresa_codigo || 'TRAMEC') + ':' + product.id;
                    var row = document.querySelector('#products-tbody tr[data-product-id="' + key + '"]');
                    if (!row) {
                        return;
                    }
                    row.querySelector('.col-codigo').textContent = product.codigo ?? '-';
                    row.classList.add('table-warning');
                    setTimeout(function () { row.classList.remove('table-warning'); }, 2000);
                });
            })();
        </script>
    @endif

    @include('partials.copy-price')
    <script src="{{ asset('dash-assets/js/busqueda-en-vivo.js').'?v='.filemtime(public_path('dash-assets/js/busqueda-en-vivo.js')) }}" defer></script>
    @if($seleccion)
        <script src="{{ asset('dash-assets/js/seleccion-masiva.js').'?v='.filemtime(public_path('dash-assets/js/seleccion-masiva.js')) }}" defer></script>
    @endif

</x-layouts.dashboard>
