<x-layouts.dashboard>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                        <span>Manuales y Catálogos</span>
                        <small class="text-muted">Los archivos se descargan desde tramec.mx</small>
                    </div>

                    <div class="card-body">
                        <input type="search" id="manuales-filtro" class="form-control mb-4" style="max-width: 360px;" placeholder="Buscar manual o catálogo…" autocomplete="off">

                        @foreach($secciones as $seccion => $grupos)
                            <h5 class="mb-3">{{ $seccion }}</h5>
                            <div class="row mb-2">
                                @foreach($grupos as $grupo => $archivos)
                                    <div class="col-12 col-md-6 col-xl-4 mb-4 manual-grupo">
                                        <div class="border rounded h-100 p-3">
                                            <h6 class="mb-3">{{ $grupo }}</h6>
                                            <ul class="list-unstyled mb-0">
                                                @foreach($archivos as [$titulo, $tamano, $slug])
                                                    <li class="mb-2 manual-item" data-t="{{ mb_strtolower($seccion.' '.$grupo.' '.$titulo) }}">
                                                        <a href="{{ $base_url.$slug }}" target="_blank" rel="noopener" class="d-flex justify-content-between text-decoration-none">
                                                            <span>{{ $titulo }}</span>
                                                            <small class="text-muted text-nowrap ms-3">PDF · {{ $tamano }}</small>
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach

                        <p id="manuales-vacio" class="text-muted" hidden>No hay manuales que coincidan.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var input = document.getElementById('manuales-filtro'), vacio = document.getElementById('manuales-vacio');
            input.addEventListener('input', function () {
                var palabras = input.value.toLowerCase().split(/\s+/).filter(Boolean), alguno = false;
                Array.prototype.forEach.call(document.querySelectorAll('.manual-grupo'), function (g) {
                    var visibles = 0;
                    Array.prototype.forEach.call(g.querySelectorAll('.manual-item'), function (li) {
                        var ok = palabras.every(function (w) { return li.getAttribute('data-t').indexOf(w) >= 0; });
                        li.hidden = !ok;
                        if (ok) { visibles++; }
                    });
                    g.hidden = visibles === 0;
                    if (visibles) { alguno = true; }
                });
                vacio.hidden = alguno;
            });
        })();
    </script>

</x-layouts.dashboard>
