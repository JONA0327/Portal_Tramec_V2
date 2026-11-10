{{-- Cotizador: datos generales de la cotización + renglones del carrito (agregados desde Productos y Existencias). --}}
<x-layouts.dashboard>

    @php $c = $cotizacion; @endphp

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                        <span>Carrito cotizador</span>
                        <small class="text-muted">{{ $c->items->count() }} renglón(es) en el carrito</small>
                    </div>

                    <div class="card-body">
                        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
                        @if($errors->any())
                            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                        @endif

                        <form id="form-cot" method="POST" action="{{ route('cotizador.guardar') }}">
                            @csrf

                            <div id="paso-carrito" {{ $errors->any() ? 'hidden' : '' }}>
                            <h6 class="mb-3">Productos en el carrito</h6>

                                <div class="mb-3" style="max-width: 420px;">
                                    <label class="form-label" for="precio_base">Canal (precio que se cotiza)</label>
                                    <select id="precio_base" name="precio_base" class="form-control">
                                        @foreach($columnas as $clave => $etiqueta)
                                            <option value="{{ $clave }}" @selected(old('precio_base', $c->precio_base) === $clave)>{{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Al elegir el canal, los renglones toman el precio de ese canal. Puedes cambiar el precio de cada renglón a mano.</div>
                                </div>


                            @if($c->items->isEmpty())
                                <p class="text-muted">El carrito está vacío. Marca productos en <a href="{{ route('products') }}">Productos</a> o <a href="{{ route('existencias') }}">Existencias</a> y usa "Agregar al carrito de cotización".</p>
                            @endif

                            @if(count($grupos) > 1)
                                <div class="alert alert-info">
                                    Hay productos en <strong>{{ implode(' y ', array_keys($grupos)) }}</strong>: una cotización no mezcla monedas, así que cada moneda
                                    se descarga como un PDF aparte (el folio lleva el sufijo de la moneda).
                                </div>
                            @endif

                            @foreach($grupos as $moneda => $g)
                                <div class="mb-4 cot-grupo" data-moneda="{{ $moneda }}">
                                    <h6 class="mb-2"><span class="badge bg-dark text-white">{{ $moneda }}</span> <small class="text-muted">{{ $g['items']->count() }} renglón(es)</small></h6>

                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle mb-2">
                                            <thead>
                                                <tr>
                                                    <th>Código</th>
                                                    <th style="min-width: 240px;">Descripción</th>
                                                    <th style="width: 90px;">Cant.</th>
                                                    <th style="width: 80px;">Uds.</th>
                                                    <th style="width: 150px;">Tiempo de entrega</th>
                                                    <th style="width: 130px;">Precio unitario</th>
                                                    <th class="text-end" style="width: 130px;">Importe</th>
                                                    <th style="width: 44px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($g['items'] as $it)
                                                    @php $pu = $it->precioUnitario(old('precio_base', $c->precio_base)); @endphp
                                                    <tr class="cot-fila" data-moneda="{{ $moneda }}" data-precios='@json($it->precios ?? [])' data-manual="{{ $it->precio_manual !== null ? 1 : 0 }}">
                                                        <td class="text-nowrap"><strong>{{ $it->codigo }}</strong></td>
                                                        <td><input type="text" name="items[{{ $it->id }}][descripcion]" value="{{ old("items.{$it->id}.descripcion", $it->descripcion) }}" class="form-control" maxlength="500"></td>
                                                        <td><input type="number" name="items[{{ $it->id }}][cantidad]" value="{{ old("items.{$it->id}.cantidad", rtrim(rtrim(number_format($it->cantidad, 2, '.', ''), '0'), '.')) }}" class="form-control cot-cant" min="0.01" step="any" required></td>
                                                        <td><input type="text" name="items[{{ $it->id }}][unidad]" value="{{ old("items.{$it->id}.unidad", $it->unidad) }}" class="form-control" maxlength="20"></td>
                                                        <td><input type="text" name="items[{{ $it->id }}][tiempo_entrega]" value="{{ old("items.{$it->id}.tiempo_entrega", $it->tiempo_entrega) }}" class="form-control" maxlength="100"></td>
                                                        <td>
                                                            <input type="number" name="items[{{ $it->id }}][precio]" value="{{ old("items.{$it->id}.precio", number_format($pu, 2, '.', '')) }}" class="form-control cot-precio {{ $pu <= 0 ? 'is-invalid' : '' }}" min="0" step="0.01">
                                                            @if($pu <= 0)<small class="text-danger sin-precio">Sin precio</small>@endif
                                                        </td>
                                                        <td class="text-end text-nowrap cot-importe">{{ number_format($it->importe(old('precio_base', $c->precio_base)), 2) }}</td>
                                                        <td>
                                                            <button type="submit" name="_method" value="DELETE" formaction="{{ route('cotizador.quitar', $it) }}" formnovalidate
                                                                class="btn btn-sm btn-limpiar" title="Quitar del carrito" aria-label="Quitar {{ $it->codigo }}">×</button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="d-flex flex-wrap justify-content-end align-items-start" style="gap: 12px;">
                                        <table class="table table-sm table-bordered mb-0 cot-totales" style="width: auto; min-width: 260px;">
                                            <tr><td>Subtotal</td><td class="text-end cot-sub">{{ number_format($g['subtotal'], 2) }}</td></tr>
                                            <tr><td>Descuento</td><td class="text-end cot-desc">{{ number_format($g['descuento'], 2) }}</td></tr>
                                            <tr><td>IVA</td><td class="text-end cot-iva">{{ number_format($g['iva'], 2) }}</td></tr>
                                            <tr><td><strong>Total {{ $moneda }}</strong></td><td class="text-end"><strong class="cot-total">{{ number_format($g['total'], 2) }}</strong></td></tr>
                                        </table>
                                    </div>
                                </div>
                            @endforeach

                                @if($c->items->isNotEmpty())
                                    <button type="button" class="btn btn-primary" data-paso="datos">Generar cotización →</button>
                                @endif
                            </div>

                            <div id="paso-datos" {{ $errors->any() ? '' : 'hidden' }}>
                                <button type="button" class="btn btn-sm btn-limpiar mb-3" data-paso="carrito">← Volver al carrito</button>
                            <h6 class="mb-3">Datos de la cotización</h6>
                            <div class="row">
                                <div class="col-6 col-lg-3 mb-3">
                                    <label class="form-label" for="fecha">Fecha</label>
                                    <input type="date" id="fecha" name="fecha" class="form-control" value="{{ old('fecha', $c->fecha?->format('Y-m-d')) }}" required>
                                </div>
                                <div class="col-6 col-lg-3 mb-3">
                                    <label class="form-label" for="folio">Folio</label>
                                    <input type="text" id="folio" name="folio" class="form-control" value="{{ old('folio', $c->folio) }}" maxlength="60" placeholder="Automático (COT-00001...) si lo dejas vacío">
                                </div>
                                <div class="col-6 col-lg-3 mb-3">
                                    <label class="form-label" for="vigencia">Vigencia</label>
                                    <input type="text" id="vigencia" name="vigencia" class="form-control" value="{{ old('vigencia', $c->vigencia) }}" maxlength="60" placeholder="Ej.: 30 Días.">
                                </div>
                                <div class="col-6 col-lg-3 mb-3">
                                    <label class="form-label" for="condiciones_pago">Condiciones de pago</label>
                                    <input type="text" id="condiciones_pago" name="condiciones_pago" class="form-control" value="{{ old('condiciones_pago', $c->condiciones_pago) }}" maxlength="191">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-lg-4 mb-3">
                                    <label class="form-label" for="cliente_empresa">Cliente (empresa)</label>
                                    <input type="text" id="cliente_empresa" name="cliente_empresa" class="form-control" value="{{ old('cliente_empresa', $c->cliente_empresa) }}" maxlength="191">
                                </div>
                                <div class="col-6 col-lg-4 mb-3">
                                    <label class="form-label" for="cliente_atencion">Atención</label>
                                    <input type="text" id="cliente_atencion" name="cliente_atencion" class="form-control" value="{{ old('cliente_atencion', $c->cliente_atencion) }}" maxlength="191" placeholder="A quien corresponda.">
                                </div>
                                <div class="col-6 col-lg-4 mb-3">
                                    <label class="form-label" for="cliente_telefono">Teléfono</label>
                                    <input type="text" id="cliente_telefono" name="cliente_telefono" class="form-control" value="{{ old('cliente_telefono', $c->cliente_telefono) }}" maxlength="60">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-lg-4 mb-3">
                                    <label class="form-label" for="emisor">Emisor de la cotización</label>
                                    <input type="text" id="emisor" name="emisor" class="form-control" value="{{ old('emisor', $c->emisor) }}" maxlength="191">
                                </div>
                                <div class="col-6 col-lg-4 mb-3">
                                    <label class="form-label" for="emisor_email">Correo del emisor</label>
                                    <input type="text" id="emisor_email" name="emisor_email" class="form-control" value="{{ old('emisor_email', $c->emisor_email) }}" maxlength="191">
                                </div>
                                <div class="col-6 col-lg-4 mb-3">
                                    <label class="form-label" for="emisor_telefono">Teléfono / Cel del emisor</label>
                                    <input type="text" id="emisor_telefono" name="emisor_telefono" class="form-control" value="{{ old('emisor_telefono', $c->emisor_telefono) }}" maxlength="60">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-6 col-lg-3 mb-3">
                                    <label class="form-label" for="descuento_pct">Descuento %</label>
                                    <input type="number" id="descuento_pct" name="descuento_pct" class="form-control" step="0.01" min="0" max="100" value="{{ old('descuento_pct', rtrim(rtrim(number_format($c->descuento_pct, 2, '.', ''), '0'), '.') ?: 0) }}">
                                </div>
                                <div class="col-6 col-lg-3 mb-3">
                                    <label class="form-label" for="iva_pct">IVA</label>
                                    @php $ivaActual = (float) old('iva_pct', $c->iva_pct); @endphp
                                    <select id="iva_pct" name="iva_pct" class="form-control">
                                        <option value="16" @selected($ivaActual === 16.0)>16 %</option>
                                        <option value="0" @selected($ivaActual === 0.0)>0 %</option>
                                        @if(! in_array($ivaActual, [16.0, 0.0], true))<option value="{{ $ivaActual }}" selected>{{ $ivaActual }} %</option>@endif
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label" for="terminos">Términos y condiciones</label>
                                <textarea id="terminos" name="terminos" class="form-control" rows="5" maxlength="3000" style="height: auto;">{{ old('terminos', $c->terminos) }}</textarea>
                            </div>

                                <div class="d-flex flex-wrap mb-3" style="gap: 8px;">
                                    @foreach($grupos as $moneda => $g)
                                        <button type="submit" formaction="{{ route('cotizador.pdf', $moneda) }}" class="btn btn-primary">Guardar y descargar PDF ({{ $moneda }})</button>
                                    @endforeach
                                </div>
                            </div>

                            @if($c->items->isNotEmpty())
                            <div class="d-flex flex-wrap" style="gap: 8px;">
                                <button type="submit" class="btn btn-secondary">Guardar cambios</button>
                            </div>
                            @endif
                        </form>

                        @if($c->items->isNotEmpty())
                            <form method="POST" action="{{ route('cotizador.vaciar') }}" class="mt-3"
                                onsubmit="return confirm('¿Vaciar el carrito y empezar una cotización nueva? Se pierden los renglones y los datos capturados.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-limpiar">Vaciar carrito / cotización nueva</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('dash-assets/js/cotizador.js') }}" defer></script>

</x-layouts.dashboard>
