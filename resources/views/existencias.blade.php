{{-- Existencias: cantidad por producto+almacén, en vivo desde StockLevelService
     (caché + sync completo cada 15 min + webhook en tiempo real desde
     servicio-stock::_vigilar_existencias). Las cantidades no se editan acá --
     son 100% información del ERP -- pero sí se ven los precios por canal (tabla
     local `products`), de solo lectura también, con copiar-al-portapapeles
     igual que en v1 (ver portal-tramec/public/dash-assets/js/copy-price.js). --}}
<x-layouts.dashboard>

    @php
        $columnasPrecio = [
            'canal_tmc' => 'CANAL TMC',
            'canal' => 'CANAL',
            'oem' => 'OEM',
            'usuario_final' => 'USUARIO FINAL',
            'precio_lista' => 'Precio de Lista',
        ];
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
                            @if($counts['conflictos'] > 0)
                                &nbsp;·&nbsp;<a href="{{ route('existencias.inconsistencias') }}" class="text-warning">⚠ {{ $counts['conflictos'] }} inconsistencia(s) pendiente(s)</a>
                            @endif
                            @if($counts['ocultos'] > 0)
                                &nbsp;·&nbsp;<a href="{{ route('existencias', ['estado' => 'ocultos']) }}" class="text-muted">{{ $counts['ocultos'] }} ocultos</a>
                            @endif
                            @if($counts['bajas'] > 0)
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
                        @can('admin')
                            <form method="POST" action="{{ route('existencias.importar') }}" enctype="multipart/form-data" class="mb-3 d-flex flex-wrap align-items-center" style="gap: 8px;">
                                @csrf
                                <select name="almacen" class="form-control" style="max-width: 220px;" aria-label="Almacén a importar" required>
                                    @foreach($almacenesImportables as $w)
                                        <option value="{{ $w->id }}" @selected($w->name === 'DELTA Schenker')>{{ $w->alias ?: $w->name }} ({{ strtoupper($w->idERP_empresa) }})</option>
                                    @endforeach
                                </select>
                                <input type="file" name="archivo" accept=".xlsx" class="form-control" style="max-width: 300px;" required>
                                <button type="submit" class="btn btn-primary">Revisar Excel</button>
                            </form>
                        @endcan

                        <form method="GET" action="{{ route('existencias') }}" class="mb-3 d-flex flex-wrap" style="max-width: 900px; gap: 8px;">
                            <select name="empresa" class="form-control" style="max-width: 170px;" onchange="this.form.submit()">
                                <option value="">Todas las empresas</option>
                                @foreach($empresas as $empresaOption)
                                    <option value="{{ $empresaOption }}" @selected($empresa === $empresaOption)>{{ $empresaOption }}</option>
                                @endforeach
                            </select>
                            <select name="almacen" class="form-control" style="max-width: 200px;" onchange="this.form.submit()">
                                <option value="">Todos los almacenes</option>
                                @foreach($almacenes as $almacenOption)
                                    <option value="{{ $almacenOption }}" @selected($almacen === $almacenOption)>{{ $almacenOption }}</option>
                                @endforeach
                            </select>
                            <select name="estado" class="form-control" style="max-width: 200px;" onchange="this.form.submit()">
                                <option value="con_existencia" @selected($estado === 'con_existencia')>Solo con existencia</option>
                                <option value="todos" @selected($estado === 'todos')>Todos (incluye en 0)</option>
                                <option value="ocultos" @selected($estado === 'ocultos')>Ocultos</option>
                                <option value="bajas" @selected($estado === 'bajas')>Dados de baja</option>
                            </select>
                            <input type="text" name="buscar" id="buscar-input" value="{{ $buscar }}" class="form-control"
                                placeholder="Buscar por código o nombre..." style="max-width: 260px;">
                        </form>

                        <script>
                            // Busca solo mientras escribís (mismo comportamiento que en Productos):
                            // el botón "Buscar" queda para los selects de arriba, que ya se
                            // auto-envían con onchange.
                            (function () {
                                var input = document.getElementById('buscar-input');
                                var timeoutId;
                                input.addEventListener('input', function () {
                                    clearTimeout(timeoutId);
                                    timeoutId = setTimeout(function () {
                                        input.form.submit();
                                    }, 450);
                                });
                            })();
                        </script>

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
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Nombre</th>
                                            <th>Empresa</th>
                                            <th>Almacén</th>
                                            <th>Cantidad</th>
                                            @foreach($columnasPrecio as $label)
                                                <th>{{ $label }}</th>
                                            @endforeach
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="existencias-tbody">
                                        @foreach($levels as $row)
                                            <tr data-level-id="{{ $row['empresa'].':'.$row['id_producto'].':'.$row['id_almacen'] }}">
                                                <td class="col-codigo">{{ $row['codigo'] }}</td>
                                                <td class="col-nombre">{{ $row['nombre'] }}</td>
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
                                                    <td class="text-nowrap {{ $row[$key] !== null ? 'copy-price-cell' : '' }} {{ isset($row['originales'][$key]) ? 'precio-ajustado' : '' }}"
                                                        @isset($row['originales'][$key]) title="Precio original: {{ number_format($row['originales'][$key], 2, '.', ',') }} (ajustado por regla)" @endisset
                                                        data-modelo="{{ $row['codigo'] }}" data-descripcion="{{ $row['nombre'] }}">
                                                        @if($row[$key] !== null)
                                                            {{ number_format($row[$key], 2, '.', ',') }}&nbsp;{{ $row['moneda'] }}
                                                            <button type="button" class="copy-price-btn" title="Copiar Código, Nombre y {{ $label }}">
                                                                <i class="fas fa-copy" aria-hidden="true"></i>
                                                            </button>
                                                        @else
                                                            -
                                                        @endif
                                                    </td>
                                                @endforeach
                                                <td class="text-nowrap">
                                                    @php
                                                        $rowUrlBase = route('existencias.edit', [$row['empresa'], $row['id_producto'], $row['id_almacen']]);
                                                    @endphp
                                                    <a href="{{ $rowUrlBase }}" class="btn btn-sm btn-primary">Editar</a>
                                                    @if(!$row['de_baja'])
                                                        <a href="{{ route('existencias.confirm-baja', [$row['empresa'], $row['id_producto'], $row['id_almacen']]) }}" class="btn btn-sm btn-danger">Dar de baja</a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

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
