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
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Folio</th><th>Empresa</th><th>Estado</th><th>Emisión</th><th class="text-end">Total</th><th></th></tr></thead>
            <tbody id="rows"></tbody>
        </table>
    </div>
</div>
<script>
crmApi("api/cotizaciones.php").then(function (d) {
  var isAdmin = window.crmRol === "admin";
  document.getElementById("rows").innerHTML = (d.cotizaciones||[]).map(function (c) {
    var folioBtn = (isAdmin && c.folio_editable)
      ? '<button class="btn btn-sm btn-outline-secondary me-1" type="button" data-folio="'+c.id+'" data-actual="'+crmEsc(c.folio)+'">Nº</button>'
      : "";
    return '<tr><td><a href="cotizacion.php?id='+c.id+'">'+crmEsc(c.folio)+'</a></td><td>'+crmEsc(c.razon_social)+'</td><td>'+crmEsc(c.estado)+'</td><td>'+crmEsc(c.fecha_emision)+'</td><td class="text-end">'+crmClp(c.total)+'</td>' +
      '<td class="text-nowrap">'+folioBtn +
      '<a class="btn btn-sm btn-outline-primary me-1" href="api/cotizacion_pdf.php?id='+c.id+'" target="_blank">PDF</a>' +
      '<button class="btn btn-sm btn-outline-danger" type="button" data-del="'+c.id+'">Eliminar</button></td></tr>';
  }).join("");
}).catch(function (e) { crmToast(e.message, true); });
document.getElementById("rows").addEventListener("click", function (ev) {
  var folioId = ev.target.getAttribute("data-folio");
  if (folioId) {
    var actual = ev.target.getAttribute("data-actual") || "";
    var nuevo = window.prompt("Nuevo número de cotización (COT-YYYY-NNNN). Solo si aún no está procesada.", actual);
    if (nuevo == null) return;
    crmApi("api/cotizacion_folio.php?id="+folioId, { method: "PUT", body: { id: Number(folioId), nuevo_numero: nuevo } })
      .then(function (d) {
        var link = ev.target.closest("tr").querySelector("td a");
        if (link && d.cotizacion) link.textContent = d.cotizacion.folio;
        ev.target.setAttribute("data-actual", d.cotizacion.folio);
        if (!d.cotizacion.folio_editable) ev.target.remove();
        crmToast(d.message || "Folio actualizado");
      })
      .catch(function (e) { crmToast(e.message, true); });
    return;
  }
  var id = ev.target.getAttribute("data-del");
  if (!id) return;
  if (!window.confirm("¿Eliminar esta cotización y sus ítems?")) return;
  crmApi("api/cotizaciones.php?id="+id, { method: "DELETE" })
    .then(function () { ev.target.closest("tr").remove(); crmToast("Cotización eliminada"); })
    .catch(function (e) { crmToast(e.message, true); });
});
</script>
<?php crm_layout_end(); ?>
