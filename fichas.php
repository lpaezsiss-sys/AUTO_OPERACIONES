<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

crm_layout_start('Fichas', 'fichas');
?>
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="page-title h3 mb-1">Fichas de producto</h1>
        <p class="text-secondary mb-0">SKU COMEX vinculados a <code>prod.db</code>.</p>
    </div>
</div>
<div class="card card-soft p-3">
    <div class="table-responsive">
        <table class="table table-sm table-landed" id="tablaFichas">
            <thead><tr><th>SKU</th><th>Nombre</th><th>Stock</th><th>Vinculado</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<script>
crmApi("api/fichas.php").then(function (d) {
  var rows = d.fichas || [];
  document.querySelector("#tablaFichas tbody").innerHTML = rows.map(function (f) {
    return "<tr><td><code>" + crmEsc(f.sku) + "</code></td><td>" + crmEsc(f.nombre) +
      "</td><td>" + crmEsc(f.stock) + "</td><td>" + (f.vinculado ? "sí" : "no") + "</td></tr>";
  }).join("") || '<tr><td colspan="4" class="text-secondary">Sin fichas. Sincronice inventario.</td></tr>';
}).catch(function (e) { crmToast(e.message, true); });
</script>
<?php crm_layout_end(); ?>
