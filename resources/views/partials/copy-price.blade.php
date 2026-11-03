    {{-- Copiar Código/Nombre/Precio al portapapeles, portado tal cual de
         portal-tramec/public/dash-assets/{js/copy-price.js,css/copy-price.css}. --}}
    <style>
        .copy-price-cell { cursor: pointer; position: relative; }
        .copy-price-cell:hover { background-color: #eaf4ff; }
        .copy-price-btn {
            display: inline-block; margin-left: 8px; padding: 0;
            width: 20px; height: 20px; line-height: 20px;
            border: none; border-radius: 4px; background-color: transparent;
            color: #2196f3; font-size: 12px; cursor: pointer;
        }
        .precio-ajustado { box-shadow: inset 3px 0 0 #0dcaf0; }
        .copy-price-cell:hover .copy-price-btn { background-color: #d6ebff; }
        .copy-price-btn:hover { color: #0d6efd; }
        .copy-price-toast {
            position: absolute; background-color: #2f9e44; color: #fff;
            padding: 4px 10px; border-radius: 4px; font-size: 12px;
            white-space: nowrap; pointer-events: none; z-index: 9999;
            opacity: 0; transform: translateY(-4px);
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        .copy-price-toast.show { opacity: 1; transform: translateY(-8px); }
    </style>
    <script>
        {{-- jQuery se carga más abajo en el layout (después de $slot), por eso se
             espera a DOMContentLoaded en vez de llamar $(...) directo acá arriba. --}}
        document.addEventListener('DOMContentLoaded', function () {
        $(document).on('click', '.copy-price-cell', function () {
            var $cell = $(this);
            var precioTexto = $cell.clone().find('.copy-price-btn').remove().end().text().trim().replace(/\s+/g, ' ');
            var text = $cell.data('modelo') + '\n' + $cell.data('descripcion') + '\n' + '$' + precioTexto + ' + IVA' + ($cell.data('existencia') ? '\n' + $cell.data('existencia') : '');

            function mostrarCopiado() {
                var offset = $cell.offset();
                var $toast = $('<div class="copy-price-toast">¡Copiado!</div>').appendTo('body');
                $toast.css({
                    top: offset.top - $toast.outerHeight() - 6,
                    left: offset.left + ($cell.outerWidth() / 2) - ($toast.outerWidth() / 2),
                });
                setTimeout(function () { $toast.addClass('show'); }, 10);
                setTimeout(function () {
                    $toast.removeClass('show');
                    setTimeout(function () { $toast.remove(); }, 200);
                }, 1200);
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(mostrarCopiado);
            } else {
                var $tmp = $('<textarea>').val(text).css({ position: 'fixed', opacity: 0 }).appendTo($('body'));
                $tmp.select();
                document.execCommand('copy');
                $tmp.remove();
                mostrarCopiado();
            }
        });
        });
    </script>
