<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

crm_layout_start('Operaciones', 'operaciones');
?>
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="page-title h3 mb-1">Operaciones</h1>
        <p class="text-secondary mb-0">Importaciones y exportaciones vinculadas a inventario.</p>
    </div>
</div>
<div class="card card-soft p-3">
    <div class="table-responsive">
        <table class="table table-sm table-landed" id="tablaOps">
            <thead><tr><th>Folio</th><th>Tipo</th><th>Estado</th><th>Fecha</th><th></th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<script>
crmApi("api/operaciones.php").then(function (d) {
  var rows = d.operaciones || [];
  document.querySelector("#tablaOps tbody").innerHTML = rows.map(function (op) {
    return "<tr><td><code>" + crmEsc(op.folio) + "</code></td><td>" + crmEsc(op.tipo) +
      "</td><td>" + crmEsc(op.estado) + "</td><td>" + crmEsc(op.fecha) +
      '</td><td><a href="landed.php?operacion_id=' + op.id + '">Landed cost</a></td></tr>';
  }).join("") || '<tr><td colspan="5" class="text-secondary">Sin operaciones.</td></tr>';
}).catch(function (e) { crmToast(e.message, true); });
</script>
<?php crm_layout_end(); ?>
