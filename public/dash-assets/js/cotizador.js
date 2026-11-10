/*
 * Cotizador: recalcula en la pantalla los importes y totales de cada moneda mientras se editan cantidades,
 * precios, descuento, IVA o la columna de precio. El servidor vuelve a calcular todo al guardar / descargar el PDF.
 */
(function () {
    'use strict';

    var form = document.getElementById('form-cot');
    if (!form) { return; }

    function num(v) { var n = parseFloat(v); return isFinite(n) ? n : 0; }
    function fmt(n) { return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function recalcular() {
        var desc = num(form.querySelector('#descuento_pct').value);
        var iva = num(form.querySelector('#iva_pct').value);

        Array.prototype.forEach.call(form.querySelectorAll('.cot-grupo'), function (g) {
            var sub = 0;

            Array.prototype.forEach.call(g.querySelectorAll('.cot-fila'), function (f) {
                var precio = num(f.querySelector('.cot-precio').value);
                var importe = Math.round(precio * num(f.querySelector('.cot-cant').value) * 100) / 100;
                f.querySelector('.cot-importe').textContent = fmt(importe);
                var aviso = f.querySelector('.sin-precio');
                if (aviso) { aviso.hidden = precio > 0; }
                f.querySelector('.cot-precio').classList.toggle('is-invalid', precio <= 0);
                sub += importe;
            });

            sub = Math.round(sub * 100) / 100;
            var d = Math.round(sub * desc) / 100;
            var i = Math.round((sub - d) * iva) / 100;
            g.querySelector('.cot-sub').textContent = fmt(sub);
            g.querySelector('.cot-desc').textContent = fmt(d);
            g.querySelector('.cot-iva').textContent = fmt(i);
            g.querySelector('.cot-total').textContent = fmt(Math.round((sub - d + i) * 100) / 100);
        });
    }

    // al cambiar la columna de precios, los renglones sin precio editado a mano toman el de esa columna
    function precioDe(f, col) {
        var precios = {};
        try { precios = JSON.parse(f.getAttribute('data-precios') || '{}'); } catch (e) { precios = {}; }
        return num(precios[col]).toFixed(2);
    }

    // el canal general se lo pone a todos los renglones
    form.querySelector('#precio_base').addEventListener('change', function () {
        var col = this.value;
        Array.prototype.forEach.call(form.querySelectorAll('.cot-fila'), function (f) {
            f.querySelector('.cot-canal').value = col;
            if (f.getAttribute('data-manual') === '1') { return; }
            f.querySelector('.cot-precio').value = precioDe(f, col);
        });
        recalcular();
    });

    // el canal de un renglón solo cambia ese renglón (y reemplaza el precio que se hubiera tecleado)
    form.addEventListener('change', function (e) {
        var t = e.target;
        if (!t.classList || !t.classList.contains('cot-canal')) { return; }
        var f = t.closest('.cot-fila');
        f.setAttribute('data-manual', '0');
        f.querySelector('.cot-precio').value = precioDe(f, t.value);
        recalcular();
    });

    // Guardado automático: poco después de cada cambio se guarda en el servidor (sin recargar la página).
    var pendiente = null;
    function guardarAhora() {
        pendiente = null;
        if (!window.fetch) { return; }
        fetch(form.getAttribute('action'), {
            method: 'POST', body: new FormData(form), credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).catch(function () { /* sin red: se guarda al descargar el PDF */ });
    }
    function modificado() { clearTimeout(pendiente); pendiente = setTimeout(guardarAhora, 700); }
    form.addEventListener('input', modificado);
    form.addEventListener('change', modificado);
    // si se sale de la página con un cambio sin guardar, se manda en ese momento
    window.addEventListener('pagehide', function () {
        if (pendiente !== null && navigator.sendBeacon) { clearTimeout(pendiente); navigator.sendBeacon(form.getAttribute('action'), new FormData(form)); }
    });

    // tocar el precio de un renglón lo marca como "manual" (ya no sigue a la columna)
    form.addEventListener('input', function (e) {
        var t = e.target;
        if (t.classList && t.classList.contains('cot-precio')) {
            t.closest('.cot-fila').setAttribute('data-manual', '1');
        }
        if (t.matches && t.matches('.cot-cant, .cot-precio, #descuento_pct')) { recalcular(); }
    });

    form.querySelector('#iva_pct').addEventListener('change', recalcular);

    // El aviso de "guardado" se quita solo.
    Array.prototype.forEach.call(document.querySelectorAll('.alert-success'), function (a) {
        setTimeout(function () { a.style.display = 'none'; }, 4000);
    });

    recalcular();

    // Condiciones de pago: la lista llena el texto, que se puede seguir modificando a mano.
    var lista = document.getElementById('condiciones_lista'), texto = document.getElementById('condiciones_pago');
    if (lista && texto) {
        lista.addEventListener('change', function () { if (lista.value) { texto.value = lista.value; } texto.focus(); });
    }

    // Tras descargar el PDF el carrito ya se vació en el servidor: se recarga la página para mostrarlo así.
    form.addEventListener('submit', function (e) {
        var b = e.submitter;
        if (b && /\/cotizador\/pdf\//.test(b.getAttribute('formaction') || '')) {
            setTimeout(function () { window.location.href = form.getAttribute('data-inicio') || window.location.pathname; }, 2500);
        }
    });

    // Dos pasos en la misma página: primero el carrito, luego los datos de la cotización y los PDF.
    form.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('[data-paso]') : null;
        if (!b) { return; }
        var datos = b.getAttribute('data-paso') === 'datos';
        document.getElementById('paso-carrito').hidden = datos;
        document.getElementById('paso-datos').hidden = !datos;
        Array.prototype.forEach.call(form.querySelectorAll('[data-paso="datos"]'), function (g) { g.hidden = datos; });
        window.scrollTo(0, 0);
    });
})();
