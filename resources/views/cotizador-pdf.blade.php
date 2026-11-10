<!DOCTYPE html>
{{-- Plantilla del PDF de cotización (basada en AM1194 KIMEX.pdf). Dompdf: solo CSS 2.1, maquetado con tablas. --}}
@php
    $azul = '#0b9ff2';
    $claro = '#cdeefb';
    $claro2 = '#e8f7fe';
    $m = fn ($v) => '$ '.number_format((float) $v, 2, '.', ',');
    $items = $g['items'];
    $vacios = max(0, 10 - $items->count()); // como en la hoja de ejemplo: mínimo 10 renglones
    $fecha = $c->fecha ? $c->fecha->format('d/m/Y').'.' : '';
@endphp
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización {{ $folio }}</title>
    <style>
        @page { margin: 26px 30px 214px 30px; } /* abajo cabe el pie fijo (cuentas, marcas y aviso) */
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #222; }
        table { border-collapse: collapse; width: 100%; }
        .azul { background: {{ $azul }}; color: #fff; font-weight: bold; }
        .c { text-align: center; } .r { text-align: right; } .b { font-weight: bold; }
        .caja th, .caja td { border: 1px solid {{ $azul }}; padding: 3px 5px; }
        .items th { background: {{ $azul }}; color: #fff; padding: 5px 3px; font-size: 8px; text-align: center; border: 1px solid #fff; }
        .items td { padding: 4px 4px; border: 1px solid {{ $azul }}; vertical-align: middle; }
        .items tr.par td { background: {{ $claro }}; } .items tr.non td { background: {{ $claro2 }}; }
        .totales td { padding: 3px 6px; border: 1px solid {{ $azul }}; }
        .ar { font-family: Helvetica, Arial, sans-serif; }
        .pie { position: fixed; left: 0; right: 0; bottom: -188px; font-family: Helvetica, Arial, sans-serif; color: #000; }
        .pie .banco td { vertical-align: top; font-size: 8.7px; line-height: 1.42; padding: 0; }
    </style>
</head>
<body>

<table>
    <tr>
        <td style="width: 55%; vertical-align: top;">
            <table>
                <tr>
                    <td style="width: 95px; vertical-align: top; padding: 0;">
                        @if($qr)<img src="{{ $qr }}" style="width: 90px; height: 90px;" alt="QR">@endif
                    </td>
                    <td class="ar" style="text-align: center; vertical-align: top; padding-top: 4px;">
                        <div class="b" style="color: #7f7f7f; font-size: 14.5px; line-height: 1.3;">TRANSMISION MECANICA Y CONTROL</div>
                        <div style="margin-top: 5px; font-size: 9.8px; color: {{ $azul }};">QUERETARO | GUADALAJARA | SAN LUIS POTOSI</div>
                        <div style="margin-top: 9px; font-size: 9.8px; color: #7f7f7f;">TEL: (442) 294 8232</div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 45%; text-align: right; vertical-align: top;">
            @if($logo)<img src="{{ $logo }}" style="width: 215px;" alt="TRAMEC">@endif
        </td>
    </tr>
</table>

<table style="margin-top: 8px;">
    <tr>
        <td style="width: 58%; vertical-align: top;">
            <table class="caja">
                <tr><th class="azul" colspan="2" style="text-align: left;">CLIENTE</th></tr>
                <tr><td class="b" style="width: 24%; background: {{ $claro }};">EMPRESA:</td><td>{{ $c->cliente_empresa }}</td></tr>
                <tr><td class="b" style="background: {{ $claro2 }};">ATENCIÓN:</td><td style="background: {{ $claro2 }};">{{ $c->cliente_atencion }}</td></tr>
                <tr><td class="b" style="background: {{ $claro }};">TELÉFONO:</td><td>{{ $c->cliente_telefono }}</td></tr>
            </table>
        </td>
        <td style="width: 42%; vertical-align: top; padding-left: 26px;">
            <div class="b c" style="color: {{ $azul }}; font-size: 16px; margin-bottom: 8px;">COTIZACIÓN</div>
            <table class="caja">
                <tr><td class="azul" style="width: 42%;">FECHA:</td><td class="b c">{{ $fecha }}</td></tr>
                <tr><td class="azul">VIGENCIA:</td><td class="b c">{{ $c->vigencia }}</td></tr>
                <tr><td class="azul">COTIZACIÓN:</td><td class="b c">{{ $folio }}</td></tr>
                <tr><td class="azul">MONEDA:</td><td class="b c">{{ $moneda }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<p style="margin: 10px 0 8px 14px; font-size: 9px;">En atención a su solicitud nos permitimos cotizar los siguientes artículos:</p>

<table class="items">
    <thead>
        <tr>
            <th style="width: 3%;">#</th>
            <th style="width: 6%;">CANT.</th>
            <th style="width: 6%;">UDS.</th>
            <th style="width: 17%;">CÓDIGO</th>
            <th>DESCRIPCIÓN</th>
            <th style="width: 11%;">TIEMPO DE ENTREGA</th>
            <th style="width: 12%;">PRECIO UNITARIO</th>
            <th style="width: 13%;">IMPORTE</th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $i => $it)
            @php $pu = $it->precioUnitario($c->precio_base); @endphp
            <tr class="{{ $i % 2 ? 'non' : 'par' }}" style="page-break-inside: avoid;">
                <td class="c">{{ $i + 1 }}</td>
                <td class="c">{{ rtrim(rtrim(number_format($it->cantidad, 2, '.', ','), '0'), '.') }}</td>
                <td class="c">{{ $it->unidad }}</td>
                <td class="c b">{{ $it->codigo }}</td>
                <td style="font-size: 8px; text-align: center;">{{ $it->descripcion }}</td>
                <td class="c" style="font-size: 8px;">{{ $it->tiempo_entrega }}</td>
                <td class="r">{{ $m($pu) }}</td>
                <td class="r">{{ $m($it->importe($c->precio_base)) }}</td>
            </tr>
        @endforeach
        @for($k = 0; $k < $vacios; $k++)
            @php $n = $items->count() + $k; @endphp
            <tr class="{{ $n % 2 ? 'non' : 'par' }}">
                <td class="c">{{ $n + 1 }}</td><td class="c">&nbsp;</td><td class="c">&nbsp;</td><td></td><td></td><td></td>
                <td class="r">$ -</td><td class="r">$ -</td>
            </tr>
        @endfor
    </tbody>
</table>

<table style="margin-top: 10px; page-break-inside: avoid;">
    <tr>
        <td style="width: 58%; vertical-align: top; padding-right: 22px;">
            <table class="caja">
                <tr><th class="azul" style="text-align: left;">TÉRMINOS Y CONDICIONES</th></tr>
                <tr><td style="height: 96px; vertical-align: top; line-height: 1.55;">{!! nl2br(e($c->terminos)) !!}</td></tr>
            </table>
        </td>
        <td style="width: 42%; vertical-align: top;">
            <table class="totales">
                <tr><td class="b c">SUBTOTAL</td><td class="r">{{ $m($g['subtotal']) }}</td></tr>
                @if($g['descuento'] > 0)
                    <tr><td class="b c">DESCUENTO {{ rtrim(rtrim(number_format($c->descuento_pct, 2), '0'), '.') }}%</td><td class="r">- {{ $m($g['descuento']) }}</td></tr>
                @endif
                <tr><td class="b c">IVA {{ rtrim(rtrim(number_format($c->iva_pct, 2), '0'), '.') }}%</td><td class="r">{{ $m($g['iva']) }}</td></tr>
                <tr><td class="b c">TOTAL</td><td class="r b">{{ $m($g['total']) }}</td></tr>
                <tr><td class="azul c" colspan="2">CONDICIONES DE PAGO</td></tr>
                <tr><td class="b c" colspan="2">{{ $c->condiciones_pago }}</td></tr>
            </table>
            <div class="c" style="margin-top: 14px; line-height: 1.7;">
                <span class="ar" style="font-size: 10px;">ATENTAMENTE</span><br>
                <span class="b" style="font-size: 10px;">{{ $c->emisor }}</span><br>
                @if($c->emisor_email)<span style="color: {{ $azul }}; text-decoration: underline;">{{ $c->emisor_email }}</span><br>@endif
                @if($c->emisor_telefono)<span class="b">Cel: {{ $c->emisor_telefono }}</span>@endif
            </div>
        </td>
    </tr>
</table>

<p style="margin: 22px 0 0 14px; font-size: 9px; width: 60%;">En espera que nuestra cotización sea de su total satisfacción y poder atender sus pedidos.</p>

{{-- Pie de página fijo: cuentas bancarias, marcas que distribuye Tramec y aviso de confirmación de pedido. --}}
<div class="pie">
    <div style="font-size: 8.7px; line-height: 1.42; padding-left: 12px;">
        CUENTAS BANCARIAS:<br>
        Beneficiario: TRANSMISION MECANICA Y CONTROL S DE RL DE CV<br>
        RFC: TMC 170306 794
    </div>
    <table class="banco" style="margin-top: 5px; width: 100%;">
        <tr>
            <td style="width: 139px; padding-left: 10px;">PESOS MEXICANOS (MXN):<br>Banco: SCOTIABANK<br>No. de Cuenta: 03505223261<br>CLABE: 044680035052232617</td>
            <td style="width: 150px;">PESOS MEXICANOS (MXN):<br>Banco: SANTANDER<br>No. de Cuenta: 65-51036949-6<br>CLABE: 014680655103694967</td>
            <td style="width: 219px;">DÓLARES (USD):<br>Banco: SCOTIABANK<br>No. de Cuenta: 95600020776<br>CLABE: 044680956000207760 SWIFT: MBCOMXMM</td>
            <td>DÓLARES (USD):<br>Banco: SANTANDER<br>No. de Cuenta: 82-50117471-6<br>CLABE: 014680825011747163</td>
        </tr>
    </table>
    @if(! empty($marcas))
        {{-- Las cinco imágenes de marcas (con su fondo blanco) tal como van en el diseño; alto y ancho de cada celda como en la hoja de ejemplo. --}}
        @php $celdas = ['delta' => [18.2, 43.5], 'omec' => [18.4, 43.5], 'stm' => [11.8, 43.5], 'wolong' => [22.6, 35], 'oli-sick' => [29, 48.5]]; @endphp
        <table style="margin-top: 6px; width: 100%;">
            <tr>
                @foreach($marcas as $clave => $img)
                    <td style="width: {{ $celdas[$clave][0] ?? 20 }}%; text-align: center; vertical-align: middle; padding: 0;"><img src="{{ $img }}" style="height: {{ $celdas[$clave][1] ?? 40 }}px;" alt="{{ $clave }}"></td>
                @endforeach
            </tr>
        </table>
    @endif
    <p style="margin: 8px 0 0 0; font-size: 9.2px; line-height: 1.3;">La confirmación o emisión de orden de compra derivada de la presente cotización formaliza el compromiso comercial entre el cliente y Transmisión Mecánica y Control. Toda transacción queda sujeta a disponibilidad de inventario y validación técnica/comercial, pudiendo existir modificaciones en tiempos de entrega o costos derivados de factores operativos o de mercado previo a su facturación final.</p>
</div>

</body>
</html>
