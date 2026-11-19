{{-- Aviso amarillo de mantenimiento programado (se apaga con PORTAL_AVISO_ACTIVO=false; el texto sale de PORTAL_AVISO_TEXTO).
     Va fijo abajo para no chocar con el encabezado y el menú. --}}
@if(config('app.aviso_activo') && config('app.aviso_texto') && ! config('app.mantenimiento'))
    <style>
        body { padding-bottom: 46px; }
        .aviso-mantenimiento {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 99999;
            background: #ffc107; color: #3d2f00; border-top: 2px solid #e0a800;
            padding: 10px 16px; text-align: center; font-size: 14px; font-weight: 600; line-height: 1.35;
        }
    </style>
    <div class="aviso-mantenimiento" role="alert">⚠ {{ config('app.aviso_texto') }}</div>
@endif
