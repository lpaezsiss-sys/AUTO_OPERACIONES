<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$id = (int) ($_GET['id'] ?? 0);
$tab = strtolower((string) ($_GET['tab'] ?? 'pipeline'));
if (!in_array($tab, ['pipeline', 'financials', 'documents', 'items'], true)) {
    $tab = 'pipeline';
}
$titles = [
    'pipeline' => 'Operación',
    'financials' => 'Finanzas',
    'documents' => 'Documentos',
    'items' => 'Ítems',
];
crm_layout_start($titles[$tab], 'operaciones');
$pipe = $tab === 'pipeline';
$fin = $tab === 'financials';
$docs = $tab === 'documents';
$itemsTab = $tab === 'items';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <a href="operaciones.php" class="small text-secondary text-decoration-none">← Pipeline</a>
        <h1 class="page-title h3 mb-1" id="tituloOp">Operación</h1>
        <p class="text-secondary mb-0" id="subOp">Detalle operativo, documental y financiero</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <div class="btn-group" role="group" aria-label="Módulo">
            <a class="btn <?php echo $pipe ? 'btn-navy' : 'btn-outline-secondary'; ?>" href="operacion.php?id=<?php echo $id; ?>">Pipeline</a>
            <a class="btn <?php echo $itemsTab ? 'btn-navy' : 'btn-outline-secondary'; ?>" href="operacion.php?id=<?php echo $id; ?>&amp;tab=items">Ítems</a>
            <a class="btn <?php echo $docs ? 'btn-navy' : 'btn-outline-secondary'; ?>" href="operacion.php?id=<?php echo $id; ?>&amp;tab=documents">Documentos</a>
            <a class="btn <?php echo $fin ? 'btn-navy' : 'btn-outline-secondary'; ?>" href="operacion.php?id=<?php echo $id; ?>&amp;tab=financials">Finanzas</a>
        </div>
        <?php if ($fin) : ?>
        <a href="manual.php#modulo-landed" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-book" aria-hidden="true"></i> Ayuda</a>
        <?php elseif ($docs) : ?>
        <a href="manual.php#modulo-documentos" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-book" aria-hidden="true"></i> Ayuda</a>
        <?php elseif ($itemsTab) : ?>
        <a href="manual.php#modulo-catalogo" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-book" aria-hidden="true"></i> Ayuda</a>
        <?php endif; ?>
        <button class="btn btn-outline-secondary" type="button" id="btnEditarOp">Editar operación</button>
        <button class="btn btn-outline-danger" type="button" id="btnEliminarOp">Eliminar operación</button>
        <div class="btn-group <?php echo $pipe ? '' : 'd-none'; ?>" id="btnsPipelineVista" role="group">
            <button class="btn btn-navy" type="button" id="btnKanban">Kanban</button>
            <button class="btn btn-outline-secondary" type="button" id="btnLista">Lista</button>
        </div>
        <button class="btn btn-yellow <?php echo $pipe ? '' : 'd-none'; ?>" type="button" id="btnAvanzar">Avanzar etapa</button>
    </div>
</div>

<div id="tabPipeline" class="<?php echo $pipe ? '' : 'd-none'; ?>">
<div id="alertaEval" class="alert alert-warning d-none" role="alert"></div>
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

<div id="tabItems" class="<?php echo $itemsTab ? '' : 'd-none'; ?>">
    <div id="alertaEvalItems" class="alert alert-warning d-none" role="alert"></div>
    <div class="card card-soft p-3 mb-3">
        <h2 class="h6 mb-2" style="color:#05294B">Agregar ítem</h2>
        <p class="small text-secondary">SKU del catálogo sincronizado o código temporal (ej. TEMP-001) con Nombre/Descripción si aún no existe en <code>prod.db</code>.</p>
        <form id="formItem">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="itemSku">SKU</label>
                    <input id="itemSku" class="form-control" list="listaSkuCatalogo" placeholder="12852-48 o TEMP-001" required>
                    <datalist id="listaSkuCatalogo"></datalist>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="itemNombre">Nombre / Descripción</label>
                    <input id="itemNombre" class="form-control" placeholder="Obligatorio si el SKU no está en inventario">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="itemCant">Cantidad</label>
                    <input id="itemCant" class="form-control" value="1" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="itemFob">FOB unit.</label>
                    <input id="itemFob" class="form-control" value="0">
                </div>
                <div class="col-12 col-md-1">
                    <button class="btn btn-yellow w-100" type="submit">Agregar</button>
                </div>
            </div>
        </form>
    </div>
    <div class="card card-soft p-3">
        <h2 class="h6 mb-2" style="color:#05294B">Ítems de la operación</h2>
        <div class="table-responsive">
            <table class="table table-sm table-landed align-middle mb-0" id="tablaItemsOp">
                <thead>
                    <tr>
                        <th>SKU</th><th>Origen</th><th>Descripción</th><th>Cant.</th><th>FOB</th><th>Stock</th><th></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalVincular" tabindex="-1" aria-labelledby="modalVincularTitulo">
    <div class="modal-dialog">
        <form class="modal-content" id="formVincular">
            <div class="modal-header">
                <h2 class="modal-title h5" id="modalVincularTitulo">Vincular al catálogo oficial</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-secondary mb-2">El SKU temporal debe existir en inventario (<code>prod.db</code>) o vincularse a un código oficial antes de Entrega/Cierre y la ENTRADA a stock.</p>
                <input type="hidden" id="vincularItemId">
                <div class="mb-2">
                    <label class="form-label">SKU temporal</label>
                    <div><code id="vincularSkuTemp"></code></div>
                </div>
                <div>
                    <label class="form-label" for="vincularSkuOficial">SKU oficial</label>
                    <input id="vincularSkuOficial" class="form-control" list="listaSkuCatalogo" placeholder="código en prod.db" required>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-yellow" type="submit">Vincular</button>
            </div>
        </form>
    </div>
</div>

<div id="tabDocumentos" class="<?php echo $docs ? '' : 'd-none'; ?>">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <p class="text-secondary mb-0">Repositorio de embarque: Factura Comercial, Packing List, BL/AWB, Certificados y DIN/DUS. Cada carga registra usuario y fecha.</p>
        <a href="manual.php#modulo-documentos" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-book" aria-hidden="true"></i> Ayuda</a>
    </div>
    <div class="row g-3 mb-3" id="docTipos"></div>
    <div class="card card-soft p-3 mb-3">
        <h2 class="h6 mb-3" style="color:#05294B">Subir documento</h2>
        <form id="formDoc" enctype="multipart/form-data">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="docTipo">Tipo</label>
                    <select id="docTipo" name="tipo" class="form-select" required>
                        <option value="FACTURA_COMERCIAL">Factura Comercial</option>
                        <option value="PACKING_LIST">Packing List</option>
                        <option value="BL_AWB">BL / AWB</option>
                        <option value="CERTIFICADO">Certificados</option>
                        <option value="DIN_DUS">DIN / DUS</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="docUsuario">Usuario</label>
                    <input id="docUsuario" name="usuario" class="form-control" value="COMEX" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="docArchivo">Archivo (PDF, JPG, PNG, WEBP · máx. 8 MB)</label>
                    <input id="docArchivo" name="archivo" class="form-control" type="file" accept=".pdf,.png,.jpg,.jpeg,.webp,application/pdf,image/*" required>
                </div>
                <div class="col-12 col-md-2">
                    <button class="btn btn-yellow w-100" type="submit">Subir</button>
                </div>
            </div>
        </form>
    </div>
    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="card card-soft p-3">
                <h2 class="h6 mb-2" style="color:#05294B">Archivos</h2>
                <div id="listaDocs" class="doc-list"></div>
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="card card-soft p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 mb-0" style="color:#05294B">Previsualización</h2>
                    <a class="small d-none" id="docDownload" href="#" target="_blank" rel="noopener">Descargar</a>
                </div>
                <div id="previewEmpty" class="text-secondary py-5 text-center">Seleccione un documento para previsualizar.</div>
                <iframe id="previewPdf" class="doc-preview d-none" title="Vista previa PDF"></iframe>
                <img id="previewImg" class="doc-preview-img d-none" alt="Vista previa">
            </div>
        </div>
    </div>
</div>

<div id="tabFinanzas" class="<?php echo $fin ? '' : 'd-none'; ?>">
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button class="btn btn-yellow" type="button" id="btnSaveEst">Guardar estimación</button>
        <button class="btn btn-navy" type="button" id="btnSaveReal">Guardar costo real</button>
        <button class="btn btn-outline-secondary" type="button" id="btnXlsx">Excel (xlsx)</button>
        <button class="btn btn-outline-secondary" type="button" id="btnPdfMx">PDF matriz</button>
        <a href="manual.php#modulo-landed" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-book" aria-hidden="true"></i> Ayuda</a>
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
<?php require __DIR__ . '/includes/modals_operacion.php'; ?>
<script>
window.COMEX_OPERACION_ID = <?php echo $id > 0 ? $id : '0'; ?>;
window.COMEX_TAB = <?php echo json_encode($tab); ?>;
window.COMEX_IVA_PCT = <?php echo json_encode(crm_iva_pct()); ?>;
window.comexOpOnDeleted = function () { location.href = "operaciones.php"; };
window.comexOpOnChanged = function () { location.reload(); };
</script>
<script src="assets/js/operacion-crud.js"></script>
<script src="assets/js/landed-calc.js"></script>
<script src="assets/js/operacion.js"></script>
<script src="assets/js/items.js"></script>
<script src="assets/js/financials.js"></script>
<script src="assets/js/documentos.js"></script>
<?php crm_layout_end(); ?>
