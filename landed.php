<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
\Auth::requireLogin();
require __DIR__ . '/includes/layout.php';

crm_layout_start('Landed cost', 'landed');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="page-title h3 mb-1">Evaluación de operaciones</h1>
        <p class="text-secondary mb-0">Landed cost Chile · prorrateo FOB · IVA aduanero <?php echo crm_h((string) crm_iva_pct()); ?>% sobre CIF</p>
    </div>
    <span class="badge-folio">Estimada vs Costo real</span>
</div>

<div class="card card-soft p-3 mb-3">
    <div class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label" for="operacion">Operación COMEX</label>
            <select id="operacion" class="form-select"></select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="version">Versión</label>
            <select id="version" class="form-select">
                <option value="ESTIMADA">Estimada</option>
                <option value="REAL">Costo real</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="moneda">FOB origen</label>
            <select id="moneda" class="form-select">
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
            </select>
        </div>
        <div class="col-md-3 d-flex flex-wrap gap-2">
            <button class="btn btn-yellow" type="button" id="btnCalcular">Calcular</button>
            <button class="btn btn-navy" type="button" id="btnGuardar">Guardar</button>
            <button class="btn btn-outline-secondary" type="button" id="btnPdf">PDF</button>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <label class="form-label" for="tcUsd">Tipo de cambio USD → CLP</label>
        <input id="tcUsd" class="form-control" value="950">
    </div>
    <div class="col-sm-6 col-xl-3">
        <label class="form-label" for="tcEur">Tipo de cambio EUR → CLP</label>
        <input id="tcEur" class="form-control" value="1050">
    </div>
    <div class="col-sm-6 col-xl-3">
        <label class="form-label" for="ivaPct">IVA aduanero %</label>
        <input id="ivaPct" class="form-control" value="<?php echo crm_h((string) crm_iva_pct()); ?>" readonly>
    </div>
    <div class="col-sm-6 col-xl-3">
        <label class="form-label" for="notas">Notas</label>
        <input id="notas" class="form-control" placeholder="Embarque / DIN / referencia">
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card card-soft p-3 h-100">
            <h2 class="h6" style="color:#05294B">Gastos</h2>
            <p class="small text-secondary">Origen (USD/EUR) entra a CIF. Locales (CLP) se prorratean por FOB y no van en el IVA.</p>
            <div class="table-responsive">
                <table class="table table-sm table-landed align-middle mb-0" id="tablaGastos">
                    <thead><tr><th>Gasto</th><th>Moneda</th><th>Monto</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card card-soft p-3 h-100">
            <h2 class="h6" style="color:#05294B">Ítems (FOB)</h2>
            <div class="table-responsive">
                <table class="table table-sm table-landed align-middle mb-0" id="tablaItems">
                    <thead><tr><th>SKU</th><th>Cant.</th><th>FOB unit.</th><th>Descripción</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1" id="kpis"></div>

<div class="card card-soft p-3 mt-3">
    <h2 class="h6" style="color:#05294B">Prorrateo y CIF</h2>
    <div class="table-responsive">
        <table class="table table-sm table-landed" id="tablaResultado">
            <thead>
                <tr>
                    <th>SKU</th><th>FOB orig.</th><th>FOB CLP</th><th>Share</th>
                    <th>CIF</th><th>IVA 19%</th><th>Locales</th><th>Landed</th><th>Unitario</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="card card-soft p-3 mt-3" id="boxComparar">
    <h2 class="h6 mb-3" style="color:#05294B">Comparación Estimada vs Costo real</h2>
    <div id="comparar" class="text-secondary small">Guarda ambas versiones para comparar.</div>
</div>
<script src="assets/js/landed.js"></script>
<?php crm_layout_end(); ?>
