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
    @endphp

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                        <span>Existencias</span>
                        <small class="text-muted">
                            @if($lastSyncAt)
                                Actualizado: {{ \Illuminate\Support\Carbon::parse($lastSyncAt)->locale('es')->diffForHumans() }}
                            @else
                                Todavía sin sincronizar
                            @endif
                            @if($esAdmin && $counts['conflictos'] > 0)
                                &nbsp;·&nbsp;<a href="{{ route('existencias.inconsistencias') }}" class="text-warning">⚠ {{ $counts['conflictos'] }} inconsistencia(s) pendiente(s)</a>
                            @endif
                            @if($esAdmin && $counts['ocultos'] > 0)
                                &nbsp;·&nbsp;<a href="{{ route('existencias', ['estado' => 'ocultos']) }}" class="text-muted">{{ $counts['ocultos'] }} ocultos</a>
                            @endif
                            @if($esAdmin && $counts['bajas'] > 0)
                                &nbsp;·&nbsp;<a href="{{ route('existencias', ['estado' => 'bajas']) }}" class="text-muted">{{ $counts['bajas'] }} de baja</a>
                            @endif
                        </small>
                    </div>

                    <div class="card-body">
                        <x-stock-status :last-sync="$lastSyncAt" />
                        @if(session('status'))
                            <div class="alert alert-success">{{ session('status') }}</div>
                        @endif

                        @error('archivo')<div class="alert alert-danger">{{ $message }}</div>@enderror

                            {{-- Avanzado: empresa + almacén + estado + palabras (en cualquier orden); busca solo al
                                 pulsar "Buscar" o Enter. "Agrupar por modelo" junta los almacenes de cada modelo en un renglón. --}}
                            <form method="GET" action="{{ route('existencias') }}" class="filtros-barra">
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
                                <select name="estado" class="form-control" aria-label="Estado">
                                    <option value="con_existencia" @selected($estado === 'con_existencia')>Solo con existencia</option>
                                    <option value="todos" @selected($estado === 'todos')>Todos (incluye en 0)</option>
                                    @if($esAdmin)
                                    <option value="ocultos" @selected($estado === 'ocultos')>Ocultos</option>
                                    <option value="bajas" @selected($estado === 'bajas')>Dados de baja</option>
                                    @endif
                                </select>
                                <select name="clasificacion" class="form-control" aria-label="Clasificación">
                                    <option value="">Todas las clasificaciones</option>
                                    @foreach($clasificaciones as $c)
                                        <option value="{{ $c }}" @selected($clasificacion === $c)>{{ $c }}</option>
                                    @endforeach
                                    <option value="__sin" @selected($clasificacion === '__sin')>Sin clasificación</option>
                                </select>
                                <input type="text" name="buscar" id="buscar-input" value="{{ $buscar }}" class="form-control filtro-buscar"
                                    placeholder="Palabras del código o descripción..." autocomplete="off">
                                <label class="filtro-check">
                                    <input type="checkbox" name="agrupar" value="1" @checked($agrupar)>
                                    Agrupar por modelo
                                </label>
                                <button type="submit" class="btn btn-primary">Buscar</button>
                                @if($buscar !== '' || $empresa !== '' || $almacen !== '' || $clasificacion !== '' || $estado !== 'con_existencia' || $agrupar)
                                    <a href="{{ route('existencias') }}" class="btn btn-outline-secondary">Limpiar</a>
                                @endif
                                @can('admin')
                                    <button type="button" class="btn btn-secondary ml-auto" data-toggle="modal" data-target="#modalExcel">Cargar Excel</button>
                                @endcan
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
                            @if($agrupar)
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
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
                                                <td class="col-codigo">{{ $row['codigo'] }}</td>
                                                <td class="col-nombre">{{ $row['descripcion'] }}</td>
                                                <td>{{ $row['empresa'] }}</td>
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
                                                <td>
                                                    @foreach($row['almacenes'] as $alm)
                                                        <{{ $esAdmin ? 'a' : 'span' }} @if($esAdmin) href="{{ route('existencias.edit', [$alm['empresa'], $alm['id_producto'], $alm['id_almacen']]) }}" @endif
                                                            class="d-inline-block border rounded px-2 py-1 mr-1 mb-1 text-nowrap text-decoration-none {{ $alm['de_baja'] ? 'text-muted' : '' }}"
                                                            @if($esAdmin) title="Editar existencia en {{ $alm['almacen'] }}" @endif>
                                                            {{ $alm['almacen'] }}:
                                                            <strong>{{ rtrim(rtrim(number_format($alm['cantidad'], 2, '.', ','), '0'), '.') ?: '0' }}</strong>
                                                            @if($esAdmin && $alm['tiene_manual'])<span title="Cantidad editada a mano">✎</span>@endif
                                                            @if($esAdmin && $alm['conflicto'])<span class="text-warning" title="El ERP cambió después de la edición manual">⚠</span>@endif
                                                            @if($alm['de_baja'])<span class="badge bg-secondary">Baja</span>@endif
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
                                                <td class="col-codigo">{{ $row['codigo'] }}</td>
                                                <td class="col-nombre">{{ $row['descripcion'] }}</td>
                                                <td>{{ $row['empresa'] }}</td>
                                                <td class="col-almacen">{{ $row['almacen'] }}</td>
                                                <td class="col-cantidad">
                                                    {{ rtrim(rtrim(number_format($row['cantidad'], 2, '.', ','), '0'), '.') ?: '0' }}
                                                    @if($row['tiene_manual'])
                                                        <span title="Cantidad editada a mano, no es la del ERP">✎</span>
                                                    @endif
                                                    @if($row['conflicto'])
                                                        <span class="text-warning" title="El ERP cambió después de esta edición manual -- ver Inconsistencias">⚠</span>
                                                    @endif
                                                    @if($row['de_baja'])
                                                        <span class="badge bg-secondary">Baja</span>
                                                    @endif
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
                                                    <a href="{{ $rowUrlBase }}" class="btn btn-sm btn-primary">Editar</a>
                                                    @if(!$row['de_baja'])
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
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Copiar Código/Nombre/Precio al portapapeles, portado tal cual de
         portal-tramec/public/dash-assets/{js/copy-price.js,css/copy-price.css}. --}}
    <style>
        .copy-price-cell { cursor: pointer; position: relative; }
        .copy-price-cell:hover { background-color: #eaf4ff; }
        .copy-price-btn {
            display: inline-block; margin-left: 8px; padding: 0;
            width: 20px; height: 20px; line-height: 20px;
            border: none; border-radius: 4px; background-color: transparent;
            color: #2196f3; font-size: 12px; cursor: pointer;
        }
        .precio-ajustado { box-shadow: inset 3px 0 0 #0dcaf0; }
        .copy-price-cell:hover .copy-price-btn { background-color: #d6ebff; }
        .copy-price-btn:hover { color: #0d6efd; }
        .copy-price-toast {
            position: absolute; background-color: #2f9e44; color: #fff;
            padding: 4px 10px; border-radius: 4px; font-size: 12px;
            white-space: nowrap; pointer-events: none; z-index: 9999;
            opacity: 0; transform: translateY(-4px);
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        .copy-price-toast.show { opacity: 1; transform: translateY(-8px); }
    </style>
    @can('admin')
    <script>
        // El modal se mueve al <body> para que no lo recorten los contenedores de la tarjeta (jQuery carga más abajo).
        document.addEventListener('DOMContentLoaded', function () {
            $('#modalExcel').appendTo('body');
        });
    </script>
    @endcan
    <script>
        {{-- jQuery se carga más abajo en el layout (después de $slot), por eso se
             espera a DOMContentLoaded en vez de llamar $(...) directo acá arriba. --}}
        document.addEventListener('DOMContentLoaded', function () {
        $(document).on('click', '.copy-price-cell', function () {
            var $cell = $(this);
            var precioTexto = $cell.clone().find('.copy-price-btn').remove().end().text().trim().replace(/\s+/g, ' ');
            var text = $cell.data('modelo') + '\n' + $cell.data('descripcion') + '\n' + '$' + precioTexto + ' + IVA';

            function mostrarCopiado() {
                var offset = $cell.offset();
                var $toast = $('<div class="copy-price-toast">¡Copiado!</div>').appendTo('body');
                $toast.css({
                    top: offset.top - $toast.outerHeight() - 6,
                    left: offset.left + ($cell.outerWidth() / 2) - ($toast.outerWidth() / 2),
                });
                setTimeout(function () { $toast.addClass('show'); }, 10);
                setTimeout(function () {
                    $toast.removeClass('show');
                    setTimeout(function () { $toast.remove(); }, 200);
                }, 1200);
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(mostrarCopiado);
            } else {
                var $tmp = $('<textarea>').val(text).css({ position: 'fixed', opacity: 0 }).appendTo($('body'));
                $tmp.select();
                document.execCommand('copy');
                $tmp.remove();
                mostrarCopiado();
            }
        });
        });
    </script>

    @if(config('broadcasting.connections.pusher.key'))
        {{-- Actualiza en vivo la fila visible cuando servicio-stock detecta un
             cambio real de cantidad (ver App\Events\ExistenciaChanged). Si la
             fila cambiada no está en la página actual, no hace nada. --}}
        <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
        <script>
            (function () {
                var pusher = new Pusher(@json(config('broadcasting.connections.pusher.key')), {
                    cluster: @json(config('broadcasting.connections.pusher.options.cluster')),
                });

                pusher.subscribe('existencias-updates').bind('existencia.changed', function (level) {
                    var key = (level.empresa_codigo || 'TRAMEC') + ':' + level.id_producto + ':' + level.id_almacen;
                    var row = document.querySelector('#existencias-tbody tr[data-level-id="' + key + '"]');
                    if (!row) {
                        return;
                    }
                    var cantidad = Number(level.cantidad ?? 0);
                    row.querySelector('.col-cantidad').textContent = cantidad % 1 === 0 ? cantidad.toFixed(0) : cantidad.toFixed(2);
                    row.classList.add('table-warning');
                    setTimeout(function () { row.classList.remove('table-warning'); }, 2000);
                });
            })();
        </script>
    @endif

</x-layouts.dashboard>
