{{-- "Mostrar N registros" (como el de DataTables en v1). Son enlaces normales (sin JavaScript): conservan los
     filtros, vuelven a la página 1 y funcionan igual con cualquier política de seguridad del navegador. --}}
@props(['actual'])
<div class="d-flex align-items-center flex-wrap mb-3" style="gap: 8px;">
    <span>Mostrar</span>
    <div class="btn-group" role="group" aria-label="Registros por página">
        @foreach(\App\Support\PorPagina::OPCIONES as $n)
            <a href="{{ request()->fullUrlWithQuery(['por_pagina' => $n, 'page' => null]) }}"
                class="btn btn-sm {{ (int) $actual === $n ? 'btn-primary' : 'btn-outline-secondary' }}"
                @if((int) $actual === $n) aria-current="true" @endif>{{ $n }}</a>
        @endforeach
    </div>
    <span>registros</span>
</div>
