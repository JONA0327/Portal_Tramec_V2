/*
 * "Convertir a MXN": abre la ventana (partials/modal-mxn) para capturar el tipo de cambio y, en el historial, el folio.
 * Carrito: botón [data-mxn-carrito]. Historial: formulario [data-mxn-historial] con data-folio y data-total.
 */
(function () {
    'use strict';

    var modal = document.getElementById('modal-mxn');
    if (!modal || !modal.showModal) { return; }

    var tc = modal.querySelector('#mxn-tc'), folio = modal.querySelector('#mxn-folio'),
        folioCaja = modal.querySelector('[data-mxn-folio-caja]'), salida = modal.querySelector('[data-mxn-total]'),
        totalUsd = 0, aceptar = null;

    function fmt(n) { return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function mostrarTotal() {
        var n = parseFloat(tc.value);
        salida.textContent = n > 0 ? '$' + fmt(totalUsd * n) : '—';
    }

    function abrir(opciones) {
        totalUsd = opciones.total;
        aceptar = opciones.aceptar;
        folioCaja.hidden = opciones.folio === null;
        folio.value = opciones.folio || '';
        tc.value = '';
        modal.querySelector('[data-mxn-ayuda]').textContent = opciones.ayuda;
        modal.querySelector('[data-mxn-aceptar]').textContent = opciones.boton;
        modal.querySelector('[data-mxn-usd]').textContent = '$' + fmt(totalUsd);
        mostrarTotal();
        modal.showModal();
        tc.focus();
    }

    tc.addEventListener('input', mostrarTotal);
    Array.prototype.forEach.call(modal.querySelectorAll('[data-mxn-cerrar]'), function (b) {
        b.addEventListener('click', function () { modal.close(); });
    });
    // El navegador ya validó el tipo de cambio (required, min, max) antes de llegar aquí.
    modal.querySelector('form').addEventListener('submit', function () {
        if (aceptar) { aceptar(tc.value, folio.value.trim()); }
    });

    document.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('[data-mxn-carrito]') : null;
        if (!b) { return; }
        e.preventDefault();
        var total = b.form.querySelector('.cot-total');
        abrir({
            total: total ? parseFloat(total.textContent.replace(/,/g, '')) || 0 : 0,
            folio: null,
            ayuda: 'Todos los precios del carrito pasarán a pesos con este tipo de cambio.',
            boton: 'Convertir carrito',
            aceptar: function (valor) {
                b.form.querySelector('[name="tipo_cambio"]').value = valor;
                b.form.requestSubmit(b); // envía con la ruta del botón, sin volver a hacer "click"
            }
        });
    });

    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f.hasAttribute || !f.hasAttribute('data-mxn-historial')) { return; }
        e.preventDefault();
        abrir({
            total: parseFloat(f.getAttribute('data-total')) || 0,
            folio: f.getAttribute('data-folio'),
            ayuda: 'Se guarda una cotización nueva en pesos y se descarga. La original no cambia.',
            boton: 'Convertir y descargar',
            aceptar: function (valor, nuevoFolio) {
                f.querySelector('[name="tipo_cambio"]').value = valor;
                f.querySelector('[name="folio"]').value = nuevoFolio;
                f.submit(); // no vuelve a disparar el evento "submit"
            }
        });
    });
})();
