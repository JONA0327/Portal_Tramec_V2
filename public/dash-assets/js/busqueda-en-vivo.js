/*
 * Búsqueda en vivo (Productos y Existencias).
 *  - La CAJA de texto busca mientras se escribe: pide al servidor la misma página con el texto y solo
 *    reemplaza el bloque #resultados (tabla + paginación). No recarga la página ni toca la caja, así que
 *    el cursor se queda donde está y se puede seguir escribiendo sin parar.
 *  - Los FILTROS (listas y casillas) siguen necesitando el botón Buscar. La búsqueda en vivo usa los filtros
 *    ya aplicados (los de la página que se ve), no los que estén a medio cambiar.
 *  - Enter en la caja también busca en vivo (no recarga la página).
 * Es un archivo externo (no JavaScript en línea) para que funcione con cualquier política de seguridad.
 */
(function () {
    'use strict';

    var form = document.querySelector('form.filtros-barra');
    var cont = document.getElementById('resultados');
    var caja = form ? form.querySelector('input[name="buscar"]') : null;

    if (!form || !cont || !caja || !window.fetch || !window.URLSearchParams || !window.DOMParser) {
        return; // navegador viejo: queda el botón Buscar de siempre
    }

    // Filtros aplicados = los que tenía el formulario al cargar la página (sin el texto de búsqueda).
    var aplicados = new URLSearchParams(new FormData(form));
    aplicados.delete('buscar');
    aplicados.delete('page');

    var timer = null;
    var controlador = null;
    var secuencia = 0;

    function urlActual() {
        var params = new URLSearchParams(aplicados.toString());
        if (caja.value.trim() !== '') { params.set('buscar', caja.value); }
        Array.from(params.keys()).forEach(function (k) {
            if (params.get(k) === '') { params.delete(k); }
        });
        var q = params.toString();
        return form.getAttribute('action') + (q ? '?' + q : '');
    }

    function cargar() {
        var mia = ++secuencia;
        var url = urlActual();

        if (controlador) { controlador.abort(); }
        controlador = window.AbortController ? new AbortController() : null;
        cont.style.opacity = '0.6';

        fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            signal: controlador ? controlador.signal : undefined
        }).then(function (r) {
            if (r.redirected && /\/login/.test(r.url)) { window.location.href = r.url; return null; } // sesión vencida
            if (!r.ok) { throw new Error('HTTP ' + r.status); }
            return r.text();
        }).then(function (html) {
            if (html === null || mia !== secuencia) { return; } // ya se pidió algo más nuevo
            var nuevo = new DOMParser().parseFromString(html, 'text/html').getElementById('resultados');
            cont.style.opacity = '';
            if (!nuevo) { return; } // respuesta inesperada: se deja lo que ya había, sin recargar ni mover el cursor

            cont.innerHTML = nuevo.innerHTML;
            document.dispatchEvent(new Event('resultados:actualizados')); // p. ej. recalcula la selección masiva
            if (window.history && history.replaceState) { history.replaceState(null, '', url); }

            // La caja está fuera de #resultados y no se toca; por si algo le quitó el foco, se devuelve.
            if (document.activeElement !== caja && document.activeElement === document.body && caja.dataset.escribiendo === '1') {
                var fin = caja.value.length;
                caja.focus();
                caja.setSelectionRange(fin, fin);
            }
        }).catch(function (e) {
            cont.style.opacity = '';
            if (e && e.name === 'AbortError') { return; }
        });
    }

    caja.addEventListener('input', function () {
        caja.dataset.escribiendo = '1';
        clearTimeout(timer);
        timer = setTimeout(cargar, 200); // una pausa mínima para no pedir una página por cada letra
    });

    caja.addEventListener('blur', function () {
        // si se salió de la caja a propósito (clic en otra cosa) ya no hay que devolverle el foco
        setTimeout(function () {
            if (document.activeElement !== document.body) { caja.dataset.escribiendo = '0'; }
        }, 0);
    });

    // Enter en la caja: busca en vivo, sin recargar la página (el botón Buscar sí envía el formulario completo)
    caja.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(timer);
            cargar();
        }
    });
})();
