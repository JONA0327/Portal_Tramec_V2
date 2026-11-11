{{-- Paginación del portal (Bootstrap 4 del tema): "Anterior / números / Siguiente" en escritorio y, en teléfono,
     solo "‹ Anterior · Página X de Y · Siguiente ›" para que quepa en una línea. --}}
@if ($paginator->hasPages())
    <nav aria-label="Paginación" class="paginacion-portal">
        <ul class="pagination justify-content-center mb-0">
            <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                @if ($paginator->onFirstPage())
                    <span class="page-link">‹ <span class="d-none d-sm-inline">Anterior</span></span>
                @else
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ <span class="d-none d-sm-inline">Anterior</span></a>
                @endif
            </li>

            <li class="page-item disabled d-md-none"><span class="page-link">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span></li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled d-none d-md-block"><span class="page-link">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active d-none d-md-block" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                        @else
                            <li class="page-item d-none d-md-block"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                @if ($paginator->hasMorePages())
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next"><span class="d-none d-sm-inline">Siguiente</span> ›</a>
                @else
                    <span class="page-link"><span class="d-none d-sm-inline">Siguiente</span> ›</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
