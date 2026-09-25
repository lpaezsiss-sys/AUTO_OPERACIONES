<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

crm_layout_start('Fichas', 'fichas');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="page-title h3 mb-1">Fichas de producto</h1>
        <p class="text-secondary mb-0">SKU COMEX vinculados a <code>prod.db</code>.</p>
    </div>
    <button class="btn btn-yellow" type="button" id="btnSyncInventario">Sincronizar Inventario</button>
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
function pintarFichas(rows) {
  document.querySelector("#tablaFichas tbody").innerHTML = (rows || []).map(function (f) {
    return "<tr><td><code>" + crmEsc(f.sku) + "</code></td><td>" + crmEsc(f.nombre) +
      "</td><td>" + crmEsc(f.stock) + "</td><td>" + (f.vinculado ? "sí" : "no") + "</td></tr>";
  }).join("") || '<tr><td colspan="4" class="text-secondary">Sin fichas. Sincronice inventario.</td></tr>';
}

function cargarFichas() {
  return crmApi("api/fichas.php").then(function (d) {
    pintarFichas(d.fichas || []);
  }).catch(function (e) { crmToast(e.message, true); });
}

cargarFichas();

document.getElementById("btnSyncInventario").addEventListener("click", function () {
  var btn = this;
  btn.disabled = true;
  fetch("api/sync.php", {
    method: "POST",
    credentials: "same-origin",
    cache: "no-store",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json"
    },
    body: "{}"
  }).then(function (res) {
    return res.json().then(function (data) {
      data = data || {};
      if (!res.ok || data.ok === false || data.success === false) {
        throw new Error(data.error || "No se pudo sincronizar inventario");
      }
      return data;
    }, function () {
      throw new Error("Respuesta inválida al sincronizar inventario");
    });
  }).then(function (data) {
    var created = Number(data.created || 0);
    var updated = Number(data.updated || 0);
    var total = Number(data.total || 0);
    crmToast("Inventario sincronizado: " + total + " SKU (" + created + " nuevos, " + updated + " actualizados).");
    return cargarFichas();
  }).catch(function (e) {
    crmToast(e.message || "Error al sincronizar", true);
  }).finally(function () {
    btn.disabled = false;
  });
});
</script>
<?php crm_layout_end(); ?>
