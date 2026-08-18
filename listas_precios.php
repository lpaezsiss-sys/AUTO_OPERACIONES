<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_require_admin();
crm_layout_start('Listas de precios', 'listas_precios', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="page-title h3 mb-1">Listas de precios</h1>
        <p class="text-secondary mb-0">Ajuste porcentual sobre el precio base de inventario. No modifica stock ni el precio en <code>productos</code>.</p>
    </div>
    <button class="btn" style="background:#fec001;color:#05294B;font-weight:700" data-bs-toggle="modal" data-bs-target="#modalLista" id="btnNueva">Nueva lista</button>
</div>
<div class="card card-soft p-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>% Ajuste</th>
                    <th>Predeterminada</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="rows"></tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="modalLista" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="formLista">
      <div class="modal-header"><h5 class="modal-title" id="titLista">Nueva lista</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
      <div class="modal-body row g-2">
        <input type="hidden" name="id" id="listaId">
        <div class="col-12"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>
        <div class="col-md-6">
            <label class="form-label">% de ajuste</label>
            <input class="form-control" name="porcentaje_ajuste" type="number" step="0.01" min="-99.99" max="999.99" value="0">
            <div class="form-text">Negativo = descuento (ej. -10). Positivo = recargo (ej. 5).</div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Estado</label>
            <select class="form-select" name="estado">
                <option value="activa">Activa</option>
                <option value="inactiva">Inactiva</option>
            </select>
        </div>
        <div class="col-12 form-check mt-2">
            <input class="form-check-input" type="checkbox" name="es_default" id="chkDefault">
            <label class="form-check-label" for="chkDefault">Lista predeterminada</label>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-danger me-auto" type="button" id="btnEliminarLista" hidden>Eliminar</button>
        <button class="btn btn-primary" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>
<script>
var listas = [];
function pctLabel(n) {
  var v = Number(n || 0);
  var s = (v > 0 ? "+" : "") + v.toFixed(2) + "%";
  return s;
}
function loadListas() {
  crmApi("api/listas_precios.php").then(function (d) {
    listas = d.listas || [];
    document.getElementById("rows").innerHTML = listas.map(function (l) {
      return "<tr>" +
        "<td>" + crmEsc(l.nombre) + "</td>" +
        "<td>" + pctLabel(l.porcentaje_ajuste) + "</td>" +
        "<td>" + (Number(l.es_default) === 1 ? "Sí" : "—") + "</td>" +
        "<td>" + (l.estado === "activa" ? "Activa" : "Inactiva") + "</td>" +
        '<td><button class="btn btn-sm btn-outline-primary" type="button" data-edit="' + l.id + '">Editar</button></td>' +
        "</tr>";
    }).join("");
  }).catch(function (e) { crmToast(e.message, true); });
}
function modoNuevo() {
  document.getElementById("formLista").reset();
  document.getElementById("listaId").value = "";
  document.getElementById("chkDefault").checked = false;
  document.getElementById("titLista").textContent = "Nueva lista";
  document.getElementById("btnEliminarLista").hidden = true;
}
document.getElementById("btnNueva").addEventListener("click", modoNuevo);
document.getElementById("rows").addEventListener("click", function (ev) {
  var raw = ev.target.getAttribute("data-edit");
  if (!raw) return;
  var l = null;
  listas.forEach(function (row) { if (String(row.id) === String(raw)) l = row; });
  if (!l) return;
  var form = document.getElementById("formLista");
  form.elements.id.value = l.id;
  form.elements.nombre.value = l.nombre || "";
  form.elements.porcentaje_ajuste.value = l.porcentaje_ajuste || 0;
  form.elements.estado.value = l.estado || "activa";
  form.elements.es_default.checked = Number(l.es_default) === 1;
  document.getElementById("titLista").textContent = "Editar lista";
  document.getElementById("btnEliminarLista").hidden = false;
  bootstrap.Modal.getOrCreateInstance(document.getElementById("modalLista")).show();
});
document.getElementById("formLista").addEventListener("submit", function (ev) {
  ev.preventDefault();
  var body = crmForm("formLista");
  body.es_default = document.getElementById("chkDefault").checked ? 1 : 0;
  var id = body.id;
  delete body.id;
  var opts = { method: id ? "PUT" : "POST", body: body };
  var url = id ? "api/listas_precios.php?id=" + encodeURIComponent(id) : "api/listas_precios.php";
  crmApi(url, opts).then(function () {
    bootstrap.Modal.getInstance(document.getElementById("modalLista")).hide();
    loadListas();
    crmToast("Lista guardada");
  }).catch(function (e) { crmToast(e.message, true); });
});
document.getElementById("btnEliminarLista").addEventListener("click", function () {
  var id = document.getElementById("listaId").value;
  if (!id || !window.confirm("¿Eliminar esta lista? Las empresas y cotizaciones quedarán sin lista asignada.")) return;
  crmApi("api/listas_precios.php?id=" + encodeURIComponent(id), { method: "DELETE" }).then(function () {
    bootstrap.Modal.getInstance(document.getElementById("modalLista")).hide();
    loadListas();
    crmToast("Lista eliminada");
  }).catch(function (e) { crmToast(e.message, true); });
});
loadListas();
</script>
<?php crm_layout_end(); ?>
