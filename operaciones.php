<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

crm_layout_start('Pipeline de operaciones', 'operaciones');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="page-title h3 mb-1">Pipeline operativo</h1>
        <p class="text-secondary mb-0">13 etapas · Evaluación y Ejecución · Importación / Exportación Chile</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <div class="btn-group" role="group" aria-label="Vista">
            <button class="btn btn-navy" type="button" id="btnKanban">Kanban</button>
            <button class="btn btn-outline-secondary" type="button" id="btnLista">Lista</button>
        </div>
        <button class="btn btn-yellow" type="button" data-bs-toggle="modal" data-bs-target="#modalCrear">Nueva operación</button>
    </div>
</div>

<div class="row g-3 mb-3" id="kpis"></div>
<div id="vistaKanban" class="pipeline-wrap"></div>
<div id="vistaLista" class="d-none">
    <div class="card card-soft p-3">
        <div class="table-responsive">
            <table class="table table-sm table-landed align-middle mb-0" id="tablaOps">
                <thead>
                    <tr>
                        <th>Folio</th><th>Tipo</th><th>Etapa actual</th><th>Fase</th>
                        <th>Responsable</th><th>Est. vs real</th><th>Avance</th><th>Alerta</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrear" tabindex="-1" aria-labelledby="modalCrearTitulo">
    <div class="modal-dialog">
        <form class="modal-content" id="formCrear">
            <div class="modal-header">
                <h2 class="modal-title h5" id="modalCrearTitulo">Crear operación</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-secondary">Al crear se generan las 13 etapas (Evaluación + Ejecución). El ítem de inventario es opcional en Solicitud.</p>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label" for="tipo">Tipo</label>
                        <select id="tipo" class="form-select" required>
                            <option value="IMPORTACION">Importación</option>
                            <option value="EXPORTACION">Exportación</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="folio">Folio</label>
                        <input id="folio" class="form-control" placeholder="auto">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="fecha">Fecha</label>
                        <input id="fecha" type="date" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="referencia">Referencia</label>
                        <input id="referencia" class="form-control" placeholder="cliente / embarque">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="sku">SKU (opcional)</label>
                        <input id="sku" class="form-control" placeholder="12852-48">
                    </div>
                    <div class="col-3">
                        <label class="form-label" for="cantidad">Cant.</label>
                        <input id="cantidad" class="form-control" value="1">
                    </div>
                    <div class="col-3">
                        <label class="form-label" for="precio">FOB</label>
                        <input id="precio" class="form-control" value="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-yellow" type="submit">Crear y sembrar pipeline</button>
            </div>
        </form>
    </div>
</div>
<script src="assets/js/operaciones.js"></script>
<?php crm_layout_end(); ?>
