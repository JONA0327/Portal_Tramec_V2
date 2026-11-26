{{-- Ventana "Convertir a MXN" (la abre convertir-mxn.js). Va fuera de cualquier otro formulario. --}}
<dialog id="modal-mxn" class="modal-mxn" aria-labelledby="modal-mxn-titulo">
    <form method="dialog">
        <div class="mxn-cabecera">
            <h5 id="modal-mxn-titulo">Convertir a MXN</h5>
            <button type="button" class="mxn-cerrar" data-mxn-cerrar aria-label="Cerrar">&times;</button>
        </div>
        <div class="mxn-cuerpo">
            <p class="mxn-ayuda" data-mxn-ayuda></p>

            <label class="form-label" for="mxn-tc">Tipo de cambio (pesos por 1 USD)</label>
            <div class="mxn-campo">
                <span>$</span>
                <input type="number" id="mxn-tc" class="form-control" min="0.0001" max="1000" step="any" inputmode="decimal" placeholder="17.50" required autocomplete="off">
            </div>

            <div data-mxn-folio-caja>
                <label class="form-label" for="mxn-folio">Folio de la cotización en MXN</label>
                <input type="text" id="mxn-folio" class="form-control" maxlength="80" autocomplete="off">
                <div class="form-text">Deja el mismo o agrégale un texto.</div>
            </div>

            <div class="mxn-resumen">
                <div><small>Total USD</small><strong data-mxn-usd></strong></div>
                <span aria-hidden="true">→</span>
                <div><small>Total MXN aprox.</small><strong data-mxn-total aria-live="polite">—</strong></div>
            </div>
        </div>
        <div class="mxn-pie">
            <button type="button" class="btn btn-limpiar" data-mxn-cerrar>Cancelar</button>
            <button type="submit" class="btn btn-primary" data-mxn-aceptar></button>
        </div>
    </form>
</dialog>

<style>
    .modal-mxn { width: min(440px, calc(100vw - 32px)); max-height: calc(100vh - 32px); padding: 0; border: 0; border-radius: 16px; box-shadow: 0 20px 60px rgba(15, 30, 60, .28); }
    .modal-mxn::backdrop { background: rgba(15, 30, 60, .45); }
    .modal-mxn form { margin: 0; }
    .mxn-cabecera { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid #e8eef5; }
    .mxn-cabecera h5 { margin: 0; font-weight: 600; }
    .mxn-cerrar { border: 0; background: none; font-size: 26px; line-height: 1; color: #6b7a8c; cursor: pointer; }
    .mxn-cuerpo { padding: 16px 20px; }
    .mxn-ayuda { color: #6b7a8c; font-size: 14px; }
    .mxn-campo { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; }
    .mxn-campo > span { font-size: 20px; color: #6b7a8c; }
    .mxn-campo .form-control { flex: 1; font-size: 18px; font-weight: 600; }
    .mxn-resumen { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 14px; padding: 12px 14px; border-radius: 12px; background: #f3f8fd; }
    .mxn-resumen small { display: block; color: #6b7a8c; }
    .mxn-resumen strong { font-size: 17px; }
    .mxn-resumen > div:last-child { text-align: right; }
    .mxn-resumen > div:last-child strong { color: #0a8adc; }
    .mxn-pie { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 8px; padding: 12px 20px 18px; }
</style>
