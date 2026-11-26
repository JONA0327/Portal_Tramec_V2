/*
 * "Convertir a MXN": pide el tipo de cambio (y, en el historial, el folio) antes de enviar el formulario.
 * Carrito: botón [data-mxn-carrito]. Historial: formulario [data-mxn-historial] con el folio actual en data-folio.
 */
(function () {
    'use strict';

    // null = canceló. Acepta "17.50" o "17,50".
    function pedirTipoCambio() {
        var texto = window.prompt('Tipo de cambio (pesos por 1 USD):', '');
        while (texto !== null) {
            var n = Number(String(texto).trim().replace(',', '.'));
            if (n > 0 && n <= 1000) { return n; }
            texto = window.prompt('Escribe un tipo de cambio válido, por ejemplo 17.50:', texto);
        }
        return null;
    }

    document.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('[data-mxn-carrito]') : null;
        if (!b) { return; }
        var tc = pedirTipoCambio();
        if (tc === null || !window.confirm('Todo el carrito pasará a MXN con tipo de cambio ' + tc + '. ¿Continuar?')) {
            e.preventDefault();
            return;
        }
        b.form.querySelector('[name="tipo_cambio"]').value = tc;
    });

    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f.hasAttribute || !f.hasAttribute('data-mxn-historial')) { return; }
        var tc = pedirTipoCambio();
        var folio = tc === null ? null : window.prompt('Folio de la cotización en MXN (deja el mismo o agrégale un texto):', f.getAttribute('data-folio'));
        if (folio === null) { e.preventDefault(); return; }
        f.querySelector('[name="tipo_cambio"]').value = tc;
        f.querySelector('[name="folio"]').value = folio.trim();
    });
})();
