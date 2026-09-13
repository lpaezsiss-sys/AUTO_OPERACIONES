<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$id = (int) ($_GET['id'] ?? 0);
crm_layout_start('Operación', 'operaciones');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <a href="operaciones.php" class="small text-secondary text-decoration-none">← Pipeline</a>
        <h1 class="page-title h3 mb-1" id="tituloOp">Operación</h1>
        <p class="text-secondary mb-0" id="subOp">13 etapas · responsable · bitácora</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <div class="btn-group" role="group">
            <button class="btn btn-navy" type="button" id="btnKanban">Kanban</button>
            <button class="btn btn-outline-secondary" type="button" id="btnLista">Lista</button>
        </div>
        <button class="btn btn-yellow" type="button" id="btnAvanzar">Avanzar etapa</button>
        <a class="btn btn-outline-secondary" id="linkLanded" href="landed.php">Landed cost</a>
    </div>
</div>
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
<script>window.COMEX_OPERACION_ID = <?php echo $id > 0 ? $id : '0'; ?>;</script>
<script src="assets/js/operacion.js"></script>
<?php crm_layout_end(); ?>
