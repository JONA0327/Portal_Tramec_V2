/*
 * Selección de varios en Productos y Existencias: casillas por fila, "seleccionar todos" de la página y botones
 * "Agregar al carrito de cotización" (administrador y equipo Tramec) y, solo administrador en Productos,
 * "Registrar seleccionados" / "Dar de baja seleccionados". Funciona por delegación de eventos, así que
 * sigue sirviendo cuando la búsqueda en vivo reemplaza la tabla. Archivo externo (sin JavaScript en línea).
 *
 * Lo marcado se conserva (sessionStorage, uno por pantalla) al cambiar de página, de registros por página, de
 * filtros o de búsqueda, y al salir y volver; solo se quita desmarcando a mano o con "Quitar selección", o
 * cuando se envía (agregar al carrito, registrar, baja). Lo marcado en otras páginas viaja en el envío.
 */
(function () {
    'use strict';

    var CLAVE = 'seleccion:' + location.pathname;
    var sel = {}; // valor de la casilla -> estado ('nuevo', ...) de lo marcado en cualquier página

    try { sel = JSON.parse(sessionStorage.getItem(CLAVE) || '{}') || {}; } catch (e) { sel = {}; }

    function guardar() {
        try { sessionStorage.setItem(CLAVE, JSON.stringify(sel)); } catch (e) { /* sin almacenamiento: solo dura la página */ }
    }

    function items() { return Array.prototype.slice.call(document.querySelectorAll('.sel-item')); }
    function cuantos(filtro) { return Object.keys(sel).filter(function (k) { return !filtro || filtro(sel[k]); }).length; }

    function aplicar() { // pinta las casillas de la página según lo guardado
        items().forEach(function (c) { c.checked = Object.prototype.hasOwnProperty.call(sel, c.value); });
    }

    function actualizar() {
        var todos = items();
        var enPagina = todos.filter(function (c) { return c.checked; });
        var total = cuantos();
        var nuevos = cuantos(function (estado) { return estado === 'nuevo'; });

        var contador = document.getElementById('sel-contador');
        if (contador) { contador.textContent = total; }

        var reg = document.querySelector('[data-masivo="registrar"]');
        if (reg) {
            reg.disabled = nuevos === 0;
            reg.textContent = 'Registrar seleccionados' + (nuevos ? ' (' + nuevos + ')' : '');
        }
        var carrito = document.querySelector('[data-carrito]');
        if (carrito) {
            carrito.disabled = total === 0;
            carrito.textContent = 'Agregar al carrito de cotización' + (total ? ' (' + total + ')' : '');
        }
        var baja = document.querySelector('[data-masivo="baja"]');
        if (baja) {
            baja.disabled = total === 0;
            baja.textContent = 'Dar de baja seleccionados' + (total ? ' (' + total + ')' : '');
        }
        var quitar = document.querySelector('[data-sel-limpiar]');
        if (quitar) { quitar.hidden = total === 0; }

        var cabecera = document.querySelector('.sel-todos');
        if (cabecera) {
            cabecera.checked = todos.length > 0 && enPagina.length === todos.length;
            cabecera.indeterminate = enPagina.length > 0 && enPagina.length < todos.length;
        }
    }

    function marcar(c, on) {
        if (on) { sel[c.value] = c.getAttribute('data-estado') || ''; } else { delete sel[c.value]; }
    }

    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !t.classList) { return; }

        if (t.classList.contains('sel-todos')) {
            items().forEach(function (c) { c.checked = t.checked; marcar(c, t.checked); });
        } else if (t.classList.contains('sel-item')) {
            marcar(t, t.checked);
        } else {
            return;
        }
        guardar();
        actualizar();
    });

    document.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('[data-masivo], [data-sel-limpiar]') : null;
        if (!b) { return; }

        if (b.hasAttribute('data-sel-limpiar')) {
            sel = {};
            guardar();
            aplicar();
            actualizar();
            return;
        }

        // confirmación de la baja masiva (el botón trae el texto en data-confirm; {n} = cantidad seleccionada)
        if (b.getAttribute('data-confirm') && !window.confirm(b.getAttribute('data-confirm').replace('{n}', cuantos()))) {
            e.preventDefault();
        }
    });

    // Al enviar: las casillas de la página viajan solas (form="form-masivo"); lo marcado en otras páginas se agrega aquí.
    // (por delegación: la búsqueda en vivo reemplaza el formulario junto con la tabla)
    document.addEventListener('submit', function (e) {
        var masivo = e.target;
        if (!masivo || masivo.id !== 'form-masivo') { return; }
        Array.prototype.forEach.call(masivo.querySelectorAll('input[data-extra]'), function (i) { i.remove(); });
        var enPagina = {};
        items().forEach(function (c) { enPagina[c.value] = true; });
        Object.keys(sel).forEach(function (valor) {
            if (enPagina[valor]) { return; }
            var h = document.createElement('input');
            h.type = 'hidden'; h.name = 'items[]'; h.value = valor; h.setAttribute('data-extra', '1');
            masivo.appendChild(h);
        });
        try { sessionStorage.removeItem(CLAVE); } catch (err) { /* nada */ }
    });

    // la búsqueda en vivo avisa cuando reemplaza la tabla
    document.addEventListener('resultados:actualizados', function () { aplicar(); actualizar(); });
    aplicar();
    actualizar();
})();
