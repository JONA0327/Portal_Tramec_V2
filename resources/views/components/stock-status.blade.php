@props(['lastSync' => null])
@php
    \App\Support\StockHealth::check();
    $since = \App\Support\StockHealth::downSince();
@endphp
<div id="stock-status" class="alert alert-warning" @if(!$since) hidden @endif>
    ⚠ El servicio de existencias no responde (desde <span id="stock-status-since">{{ $since ? \Illuminate\Support\Carbon::parse($since)->locale('es')->diffForHumans() : '' }}</span>),
    así que los datos <strong>no se muestran en tiempo real</strong>.
    @if($lastSync)
        Última actualización: {{ \Illuminate\Support\Carbon::parse($lastSync)->format('d/m/Y H:i') }}.
    @else
        Aún no hay ninguna sincronización guardada.
    @endif
</div>
<script>
    setInterval(() => {
        fetch(@json(route('stock.status')), {headers: {Accept: 'application/json'}})
            .then(r => r.json())
            .then(s => {
                document.getElementById('stock-status').hidden = !s.down;
                document.getElementById('stock-status-since').textContent = s.since ?? '';
            })
            .catch(() => {});
    }, 15000);
</script>
