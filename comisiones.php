<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Comisiones', 'comisiones', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="page-title h3 mb-0">Comisiones por cotización</h1>
</div>
<div class="card card-soft p-3 mb-3">
    <div class="row g-2">
        <div class="col-md-4">
            <select id="estado" class="form-select">
                <option value="">Todos los estados</option>
                <option value="pendiente">Pendiente</option>
                <option value="aprobada">Aprobada</option>
                <option value="pagada">Pagada</option>
                <option value="anulada">Anulada</option>
            </select>
        </div>
        <div class="col-md-4">
            <select id="vendedor_id" class="form-select">
                <option value="">Todos los vendedores</option>
            </select>
        </div>
    </div>
</div>
<div class="card card-soft p-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Cotización</th>
                    <th>Vendedor</th>
                    <th>Neto</th>
                    <th>%</th>
                    <th>Comisión</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="rows"></tbody>
        </table>
    </div>
</div>
<script>
function load() {
  var estado = document.getElementById("estado").value;
  var vend = document.getElementById("vendedor_id").value;
  crmApi("api/comisiones.php?estado="+encodeURIComponent(estado)+"&vendedor_id="+encodeURIComponent(vend)).then(function (d) {
    document.getElementById("rows").innerHTML = (d.comisiones||[]).map(function (c) {
      return '<tr>' +
        '<td>'+(c.cotizacion_folio||c.cotizacion_id)+'</td>' +
        '<td>'+c.vendedor_nombre+'</td>' +
        '<td>'+crmClp(c.monto_venta_neto)+'</td>' +
        '<td>'+Number(c.porcentaje_aplicado).toFixed(2)+'%</td>' +
        '<td>'+crmClp(c.monto_comision)+'</td>' +
        '<td>'+c.estado+'</td>' +
        '<td>' +
          '<button class="btn btn-sm btn-outline-success me-1" data-id="'+c.id+'" data-estado="aprobada">Aprobar</button>' +
          '<button class="btn btn-sm btn-outline-primary me-1" data-id="'+c.id+'" data-estado="pagada">Pagar</button>' +
          '<button class="btn btn-sm btn-outline-danger" data-id="'+c.id+'" data-estado="anulada">Anular</button>' +
        '</td>' +
        '</tr>';
    }).join("") || '<tr><td colspan="7" class="text-secondary">Sin comisiones.</td></tr>';
  }).catch(function (e) { crmToast(e.message, true); });
}
crmApi("api/vendedores.php").then(function (d) {
  var sel = document.getElementById("vendedor_id");
  (d.vendedores||[]).forEach(function (v) {
    var o = document.createElement("option");
    o.value = v.id;
    o.textContent = v.nombre_completo;
    sel.appendChild(o);
  });
});
["estado","vendedor_id"].forEach(function (id) {
  document.getElementById(id).addEventListener("change", load);
});
document.getElementById("rows").addEventListener("click", function (ev) {
  var id = ev.target.getAttribute("data-id");
  var estado = ev.target.getAttribute("data-estado");
  if (!id || !estado) return;
  crmApi("api/comisiones.php?id="+encodeURIComponent(id), { method: "PATCH", body: { estado: estado } })
    .then(function () { load(); crmToast("Comisión "+estado); })
    .catch(function (e) { crmToast(e.message, true); });
});
load();
</script>
<?php crm_layout_end(); ?>
