{{-- "Mostrar N registros" (como el de DataTables en v1). Al cambiarlo se recarga la misma página con
     ?por_pagina=N, conservando los filtros y volviendo a la página 1. --}}
@props(['actual'])
<label class="d-flex align-items-center mb-3" style="gap: 8px;">
    Mostrar
    <select class="form-control" style="width: auto; min-width: 84px;" aria-label="Registros por página"
        onchange="var u = new URL(window.location.href); u.searchParams.set('por_pagina', this.value); u.searchParams.delete('page'); window.location = u;">
        @foreach(\App\Support\PorPagina::OPCIONES as $n)
            <option value="{{ $n }}" @selected((int) $actual === $n)>{{ $n }}</option>
        @endforeach
    </select>
    registros
</label>
