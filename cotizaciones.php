<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Cotizaciones', 'cotizaciones', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="page-title h3 mb-0">Cotizaciones</h1>
    <a class="btn" style="background:#fec001;color:#05294B;font-weight:700" href="cotizador.php">Nueva cotización</a>
</div>
<div class="card card-soft p-3">
    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <label class="form-label" for="fEstado">Estado</label>
            <select id="fEstado" class="form-select">
                <option value="">Todos</option>
                <option value="borrador">Borrador</option>
                <option value="enviada">Enviada</option>
                <option value="aceptada">Aceptada</option>
                <option value="rechazada">Rechazada</option>
                <option value="vencida">Vencida</option>
            </select>
        </div>
        <div class="col-md-8">
            <label class="form-label" for="empresa_q">Empresa</label>
            <div class="crm-typeahead">
                <input type="hidden" id="fEmpresaId" value="">
                <input class="form-control" id="empresa_q" autocomplete="off" placeholder="Buscar razón social o RUT…">
                <div id="empresa_sug" class="list-group position-absolute w-100 shadow" style="display:none"></div>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Folio</th><th>Empresa</th><th>Estado</th><th>Emisión</th><th class="text-end">Total</th><th></th></tr></thead>
            <tbody id="rows"></tbody>
        </table>
    </div>
</div>
<script>
function loadCots() {
  var estado = document.getElementById("fEstado").value;
  var empId = document.getElementById("fEmpresaId").value;
  var url = "api/cotizaciones.php?";
  if (estado) url += "estado=" + encodeURIComponent(estado) + "&";
  if (empId) url += "empresa_id=" + encodeURIComponent(empId) + "&";
  var isAdmin = window.crmRol === "admin";
  crmApi(url).then(function (d) {
    var rows = d.cotizaciones || [];
    document.getElementById("rows").innerHTML = rows.map(function (c) {
      var folioBtn = (isAdmin && c.folio_editable)
        ? '<li><button class="dropdown-item" type="button" data-folio="'+c.id+'" data-actual="'+crmEsc(c.folio)+'">Cambiar número</button></li>'
        : "";
      return '<tr><td><a href="cotizacion.php?id='+c.id+'">'+crmEsc(c.folio)+'</a></td><td>'+crmEsc(c.razon_social)+'</td><td>'+crmEsc(c.estado)+'</td><td>'+crmEsc(c.fecha_emision)+'</td><td class="text-end">'+crmClp(c.total)+'</td>' +
        '<td class="text-nowrap">' +
        '<a class="btn btn-sm btn-outline-primary me-1" href="api/cotizacion_pdf.php?id='+c.id+'" target="_blank">PDF</a>' +
        '<div class="btn-group">' +
        '<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">Más</button>' +
        '<ul class="dropdown-menu dropdown-menu-end">' +
        folioBtn +
        '<li><button class="dropdown-item" type="button" data-dup="'+c.id+'">Duplicar</button></li>' +
        '<li><button class="dropdown-item text-danger" type="button" data-del="'+c.id+'">Eliminar</button></li>' +
        '</ul></div></td></tr>';
    }).join("") || '<tr><td colspan="6" class="text-secondary">No hay cotizaciones con ese filtro.</td></tr>';
  }).catch(function (e) { crmToast(e.message, true); });
}
crmEmpresaPicker({
  inputId: "empresa_q",
  hiddenId: "fEmpresaId",
  listId: "empresa_sug",
  onSelect: function () { loadCots(); }
});
document.getElementById("fEstado").addEventListener("change", loadCots);
document.getElementById("empresa_q").addEventListener("input", function () {
  if (!this.value) {
    document.getElementById("fEmpresaId").value = "";
    loadCots();
  }
});
document.getElementById("rows").addEventListener("click", function (ev) {
  var folioBtn = ev.target.closest("[data-folio]");
  if (folioBtn) {
    var folioId = folioBtn.getAttribute("data-folio");
    var actual = folioBtn.getAttribute("data-actual") || "";
    var nuevo = window.prompt("Nuevo número de cotización (COT-YYYY-NNNN o correlativo). Históricos menores a 354 se permiten si están libres.", actual);
    if (nuevo == null) return;
    crmApi("api/cotizacion_folio.php?id="+folioId, { method: "PUT", body: { id: Number(folioId), nuevo_numero: nuevo } })
      .then(function (d) {
        loadCots();
        crmToast(d.message || "Folio actualizado");
      })
      .catch(function (e) { crmToast(e.message, true); });
    return;
  }
  var dupBtn = ev.target.closest("[data-dup]");
  if (dupBtn) {
    var dupId = dupBtn.getAttribute("data-dup");
    if (!window.confirm("¿Duplicar esta cotización como un borrador nuevo?")) return;
    crmApi("api/cotizaciones.php?action=duplicar", { method: "POST", body: { id: Number(dupId) } })
      .then(function (d) {
        crmToast("Duplicada " + d.cotizacion.folio);
        window.location.href = "cotizacion.php?id=" + d.cotizacion.id;
      })
      .catch(function (e) { crmToast(e.message, true); });
    return;
  }
  var delBtn = ev.target.closest("[data-del]");
  if (!delBtn) return;
  var id = delBtn.getAttribute("data-del");
  if (!window.confirm("¿Eliminar esta cotización y sus ítems?")) return;
  crmApi("api/cotizaciones.php?id="+id, { method: "DELETE" })
    .then(function () { loadCots(); crmToast("Cotización eliminada"); })
    .catch(function (e) { crmToast(e.message, true); });
});
loadCots();
</script>
<?php crm_layout_end(); ?>
