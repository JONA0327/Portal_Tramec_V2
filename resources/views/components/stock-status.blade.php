@props(['lastSync' => null])
@php
    \App\Support\StockHealth::check();
    $since = \App\Support\StockHealth::downSince();
    $lastFail = \App\Support\SyncLog::lastFailure('levels') ?? \App\Support\SyncLog::lastFailure('products');
@endphp
<div id="stock-status" class="alert alert-warning" @if(!$since) hidden @endif>
    ⚠ <strong>Conexión en tiempo real no disponible temporalmente</strong> (desde <span id="stock-status-since">{{ $since ? \Illuminate\Support\Carbon::parse($since)->locale('es')->diffForHumans() : '' }}</span>).
    @if($lastSync)
        Última actualización (hora de México): {{ \App\Support\SyncLog::mx($lastSync)->format('d/m/Y H:i') }}.
    @else
        Aún no hay ninguna sincronización guardada.
    @endif
    @if($lastFail)
        Último intento fallido: {{ \App\Support\SyncLog::mx($lastFail)->format('d/m/Y H:i') }}.
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
