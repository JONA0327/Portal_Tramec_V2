{{-- Paginación del portal (Bootstrap 4 del tema): flechas y números en escritorio y, en teléfono,
     solo "‹ · X / Y · ›" para que quepa en una línea. --}}
@if ($paginator->hasPages())
    <nav aria-label="Paginación" class="paginacion-portal">
        <ul class="pagination justify-content-center flex-nowrap mb-0">
            <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                @if ($paginator->onFirstPage())
                    <span class="page-link" aria-label="Anterior">‹</span>
                @else
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" title="Anterior" aria-label="Anterior">‹</a>
                @endif
            </li>

            <li class="page-item disabled d-md-none"><span class="page-link text-nowrap" aria-label="Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span></li>

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
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" title="Siguiente" aria-label="Siguiente">›</a>
                @else
                    <span class="page-link" aria-label="Siguiente">›</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
