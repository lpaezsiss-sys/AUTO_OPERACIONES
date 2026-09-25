<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$id = (int) ($_GET['id'] ?? 0);
$tab = strtolower((string) ($_GET['tab'] ?? 'pipeline'));
if ($tab !== 'financials') {
    $tab = 'pipeline';
}
crm_layout_start($tab === 'financials' ? 'Finanzas' : 'Operación', 'operaciones');
$fin = $tab === 'financials';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <a href="operaciones.php" class="small text-secondary text-decoration-none">← Pipeline</a>
        <h1 class="page-title h3 mb-1" id="tituloOp">Operación</h1>
        <p class="text-secondary mb-0" id="subOp">Detalle operativo y financiero</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <div class="btn-group" role="group" aria-label="Módulo">
            <a class="btn <?php echo $fin ? 'btn-outline-secondary' : 'btn-navy'; ?>" href="operacion.php?id=<?php echo $id; ?>">Pipeline</a>
            <a class="btn <?php echo $fin ? 'btn-navy' : 'btn-outline-secondary'; ?>" href="operacion.php?id=<?php echo $id; ?>&amp;tab=financials">Finanzas</a>
        </div>
        <div class="btn-group <?php echo $fin ? 'd-none' : ''; ?>" id="btnsPipelineVista" role="group">
            <button class="btn btn-navy" type="button" id="btnKanban">Kanban</button>
            <button class="btn btn-outline-secondary" type="button" id="btnLista">Lista</button>
        </div>
        <button class="btn btn-yellow <?php echo $fin ? 'd-none' : ''; ?>" type="button" id="btnAvanzar">Avanzar etapa</button>
    </div>
</div>

<div id="tabPipeline" class="<?php echo $fin ? 'd-none' : ''; ?>">
<div class="row g-3 mb-3" id="kpis"></div>
<div id="vistaKanban" class="pipeline-wrap"></div>
<div id="vistaLista" class="d-none card card-soft p-3">
    <div class="table-responsive">
        <table class="table table-sm table-landed align-middle mb-0" id="tablaEtapas">
            <thead>
                <tr>
                    <th>#</th><th>Etapa</th><th>Fase</th><th>Estado</th>
                    <th>Responsable</th><th>Estimada</th><th>Real</th><th>Alerta</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
</div>

<div id="tabFinanzas" class="<?php echo $fin ? '' : 'd-none'; ?>">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button class="btn btn-yellow" type="button" id="btnSaveEst">Guardar estimación</button>
        <button class="btn btn-navy" type="button" id="btnSaveReal">Guardar costo real</button>
        <button class="btn btn-outline-secondary" type="button" id="btnXlsx">Excel (xlsx)</button>
        <button class="btn btn-outline-secondary" type="button" id="btnPdfMx">PDF matriz</button>
        <span class="small text-secondary align-self-center" id="sheetHint">Recálculo en vivo · IVA 19% sobre CIF · prorrateo FOB</span>
    </div>
    <div class="row g-3 mb-3" id="finKpis"></div>
    <div class="card card-soft p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label" for="finMoneda">FOB origen</label>
                <select id="finMoneda" class="form-select form-select-sm">
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="finTcUsd">TC USD → CLP</label>
                <input id="finTcUsd" class="form-control form-control-sm sheet-in" value="900">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="finTcEur">TC EUR → CLP</label>
                <input id="finTcEur" class="form-control form-control-sm sheet-in" value="1050">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="finIva">IVA aduanero %</label>
                <input id="finIva" class="form-control form-control-sm" value="<?php echo crm_h((string) crm_iva_pct()); ?>" readonly>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="finNotas">Notas</label>
                <input id="finNotas" class="form-control form-control-sm" placeholder="DIN / embarque / referencia">
            </div>
        </div>
    </div>
    <div class="card card-soft p-3 mb-3">
        <h2 class="h6 mb-2" style="color:#05294B">Gastos (Estimación vs Real)</h2>
        <p class="small text-secondary">Origen USD/EUR entra a CIF. Locales CLP se prorratean por FOB y no van en el IVA.</p>
        <div class="table-responsive">
            <table class="table table-sm table-landed sheet-table align-middle mb-0" id="sheetGastos">
                <thead>
                    <tr>
                        <th>Gasto</th><th>Ámbito</th><th>Moneda</th>
                        <th>Estimación</th><th>Costo real</th><th>Delta</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
    <div class="card card-soft p-3 mb-3">
        <h2 class="h6 mb-2" style="color:#05294B">Líneas de producto · factor FOB · CIF · IVA 19% · unitario CLP/USD</h2>
        <div class="table-responsive">
            <table class="table table-sm table-landed sheet-table align-middle mb-0" id="sheetItems">
                <thead>
                    <tr>
                        <th>SKU</th><th>Cant.</th><th>FOB unit.</th><th>Factor</th>
                        <th>CIF est</th><th>IVA est</th><th>Landed est CLP</th><th>Unit est CLP</th><th>Unit est USD</th>
                        <th>CIF real</th><th>IVA real</th><th>Landed real CLP</th><th>Unit real CLP</th><th>Unit real USD</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
    <div class="card card-soft p-3" id="boxMatriz">
        <h2 class="h6 mb-2" style="color:#05294B">Matriz Estimación vs Costo real</h2>
        <div class="table-responsive">
            <table class="table table-sm table-landed mb-0" id="sheetMatriz">
                <thead>
                    <tr><th>Concepto</th><th>Estimación</th><th>Real</th><th>Delta</th></tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="panelEtapa">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="panelTitulo">Etapa</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body">
        <form id="formEtapa">
            <input type="hidden" id="etapaId">
            <div class="mb-2">
                <label class="form-label" for="etapaEstado">Estado</label>
                <select id="etapaEstado" class="form-select">
                    <option value="PENDING">PENDING</option>
                    <option value="IN_PROGRESS">IN_PROGRESS</option>
                    <option value="COMPLETED">COMPLETED</option>
                    <option value="BLOCKED">BLOCKED</option>
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label" for="etapaResp">Responsable</label>
                <input id="etapaResp" class="form-control" placeholder="nombre / área">
            </div>
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label" for="etapaEst">Fecha estimada</label>
                    <input id="etapaEst" type="date" class="form-control">
                </div>
                <div class="col-6">
                    <label class="form-label" for="etapaReal">Fecha real</label>
                    <input id="etapaReal" type="date" class="form-control">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="etapaComentario">Bitácora / comentario</label>
                <textarea id="etapaComentario" class="form-control" rows="3" placeholder="Avance, bloqueo, evidencia…"></textarea>
            </div>
            <button class="btn btn-yellow w-100" type="submit">Guardar etapa</button>
        </form>
        <hr>
        <h3 class="h6" style="color:#05294B">Historial</h3>
        <ul class="bitacora list-unstyled small mb-0" id="listaBitacora"></ul>
    </div>
</div>
<script>
window.COMEX_OPERACION_ID = <?php echo $id > 0 ? $id : '0'; ?>;
window.COMEX_TAB = <?php echo json_encode($tab); ?>;
window.COMEX_IVA_PCT = <?php echo json_encode(crm_iva_pct()); ?>;
</script>
<script src="assets/js/landed-calc.js"></script>
<script src="assets/js/operacion.js"></script>
<script src="assets/js/financials.js"></script>
<?php crm_layout_end(); ?>
