/*
 * Selección de varios en Productos y Existencias: casillas por fila, "seleccionar todos" de la página y botones
 * "Agregar al carrito de cotización" (administrador y equipo Tramec) y, solo administrador en Productos,
 * "Registrar seleccionados" / "Dar de baja seleccionados". Funciona por delegación de eventos, así que
 * sigue sirviendo cuando la búsqueda en vivo reemplaza la tabla. Archivo externo (sin JavaScript en línea).
 */
(function () {
    'use strict';

    function items() { return Array.prototype.slice.call(document.querySelectorAll('.sel-item')); }

    function actualizar() {
        var todos = items();
        var marcados = todos.filter(function (c) { return c.checked; });
        var nuevos = marcados.filter(function (c) { return c.getAttribute('data-estado') === 'nuevo'; });

        var contador = document.getElementById('sel-contador');
        if (contador) { contador.textContent = marcados.length; }

        var reg = document.querySelector('[data-masivo="registrar"]');
        if (reg) {
            reg.disabled = nuevos.length === 0;
            reg.textContent = 'Registrar seleccionados' + (nuevos.length ? ' (' + nuevos.length + ')' : '');
        }
        var carrito = document.querySelector('[data-carrito]');
        if (carrito) {
            carrito.disabled = marcados.length === 0;
            carrito.textContent = 'Agregar al carrito de cotización' + (marcados.length ? ' (' + marcados.length + ')' : '');
        }
        var baja = document.querySelector('[data-masivo="baja"]');
        if (baja) {
            baja.disabled = marcados.length === 0;
            baja.textContent = 'Dar de baja seleccionados' + (marcados.length ? ' (' + marcados.length + ')' : '');
        }

        var cabecera = document.querySelector('.sel-todos');
        if (cabecera) {
            cabecera.checked = todos.length > 0 && marcados.length === todos.length;
            cabecera.indeterminate = marcados.length > 0 && marcados.length < todos.length;
        }
    }

    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !t.classList) { return; }

        if (t.classList.contains('sel-todos')) {
            items().forEach(function (c) { c.checked = t.checked; });
            actualizar();
        } else if (t.classList.contains('sel-item')) {
            actualizar();
        }
    });

    // confirmación de la baja masiva (el botón trae el texto en data-confirm; {n} = cantidad seleccionada)
    document.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('[data-masivo]') : null;
        if (!b || !b.getAttribute('data-confirm')) { return; }
        var n = items().filter(function (c) { return c.checked; }).length;
        if (!window.confirm(b.getAttribute('data-confirm').replace('{n}', n))) { e.preventDefault(); }
    });

    // la búsqueda en vivo avisa cuando reemplaza la tabla
    document.addEventListener('resultados:actualizados', actualizar);
    actualizar();
})();
