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
        @page { margin: 26px 30px 24px 30px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #222; }
        table { border-collapse: collapse; width: 100%; }
        .azul { background: {{ $azul }}; color: #fff; font-weight: bold; }
        .c { text-align: center; } .r { text-align: right; } .b { font-weight: bold; }
        .caja th, .caja td { border: 1px solid {{ $azul }}; padding: 3px 5px; }
        .items th { background: {{ $azul }}; color: #fff; padding: 5px 3px; font-size: 8px; text-align: center; border: 1px solid #fff; }
        .items td { padding: 4px 4px; border: 1px solid {{ $azul }}; vertical-align: middle; }
        .items tr.par td { background: {{ $claro }}; } .items tr.non td { background: {{ $claro2 }}; }
        .totales td { padding: 3px 6px; border: 1px solid {{ $azul }}; }
    </style>
</head>
<body>

<table>
    <tr>
        <td style="width: 55%; text-align: center; vertical-align: top; padding-top: 6px;">
            @if($qr)<img src="{{ $qr }}" style="width: 84px; height: 84px;" alt="QR tramec.mx">
            <div class="b" style="color: {{ $azul }}; font-size: 9px; margin-top: 2px;">tramec.mx</div>@endif
            <div style="margin-top: 8px; font-size: 9px;">QRO - GDL - SLP</div>
            <div class="b" style="margin-top: 6px; font-size: 9px;">Oficina: (442) 294 8232</div>
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
                <span class="b" style="font-size: 10px;">{{ $c->emisor }}</span><br>
                @if($c->emisor_email)<span style="color: {{ $azul }}; text-decoration: underline;">{{ $c->emisor_email }}</span><br>@endif
                @if($c->emisor_telefono)<span class="b">Cel: {{ $c->emisor_telefono }}</span>@endif
            </div>
        </td>
    </tr>
</table>

<p style="margin: 22px 0 0 14px; font-size: 9px; width: 60%;">En espera que nuestra cotización sea de su total satisfacción y poder atender sus pedidos.</p>

</body>
</html>
