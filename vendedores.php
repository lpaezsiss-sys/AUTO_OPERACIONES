<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Vendedores', 'vendedores', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="page-title h3 mb-0">Vendedores y comisiones</h1>
    <button class="btn" style="background:#fec001;color:#05294B;font-weight:700" data-bs-toggle="modal" data-bs-target="#modalVend" id="btnNuevo">Nuevo vendedor</button>
</div>
<div class="card card-soft p-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Usuario</th>
                    <th>% Comisión</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="rows"></tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="modalVend" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="formVend">
      <div class="modal-header"><h5 class="modal-title" id="titVend">Nuevo vendedor</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
      <div class="modal-body row g-2">
        <input type="hidden" name="id" id="vendId">
        <div class="col-12"><label class="form-label">Nombre completo</label><input class="form-control" name="nombre_completo" required></div>
        <div class="col-12"><label class="form-label">Email</label><input class="form-control" name="email" type="email" required></div>
        <div class="col-md-6"><label class="form-label">Teléfono</label><input class="form-control" name="telefono"></div>
        <div class="col-md-6"><label class="form-label">% Comisión</label><input class="form-control" name="comision_porcentaje" type="number" min="0" max="100" step="0.01" value="2.50"></div>
        <div class="col-12"><label class="form-label">Usuario de login (opcional)</label><select class="form-select" name="usuario_id" id="selUsuario"></select></div>
        <div class="col-12 form-check mt-2">
            <input class="form-check-input" type="checkbox" name="activo" id="chkActivo" checked>
            <label class="form-check-label" for="chkActivo">Activo</label>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary" type="submit">Guardar</button></div>
    </form>
  </div>
</div>
<script>
var usuarios = [];
var vendedores = [];
function loadVend() {
  crmApi("api/vendedores.php").then(function (d) {
    usuarios = d.usuarios || [];
    vendedores = d.vendedores || [];
    document.getElementById("selUsuario").innerHTML = '<option value="">(sin vincular)</option>' +
      usuarios.map(function (u) { return '<option value="'+u.id+'">'+u.nombre+' · '+u.email+'</option>'; }).join("");
    document.getElementById("rows").innerHTML = (d.vendedores||[]).map(function (v) {
      return '<tr>' +
        '<td>'+v.nombre_completo+'</td>' +
        '<td>'+v.email+'</td>' +
        '<td>'+(v.usuario_email||"—")+'</td>' +
        '<td>'+Number(v.comision_porcentaje).toFixed(2)+'%</td>' +
        '<td>'+(Number(v.activo)===1?'Activo':'Inactivo')+'</td>' +
        '<td><button class="btn btn-sm btn-outline-primary" type="button" data-edit="'+v.id+'">Editar</button></td>' +
        '</tr>';
    }).join("");
  }).catch(function (e) { crmToast(e.message, true); });
}
document.getElementById("btnNuevo").addEventListener("click", function () {
  document.getElementById("formVend").reset();
  document.getElementById("vendId").value = "";
  document.getElementById("chkActivo").checked = true;
  document.getElementById("titVend").textContent = "Nuevo vendedor";
});
document.getElementById("rows").addEventListener("click", function (ev) {
  var raw = ev.target.getAttribute("data-edit");
  if (!raw) return;
  var v = null;
  vendedores.forEach(function (row) { if (String(row.id) === String(raw)) v = row; });
  if (!v) return;
  var form = document.getElementById("formVend");
  form.elements.id.value = v.id;
  form.elements.nombre_completo.value = v.nombre_completo || "";
  form.elements.email.value = v.email || "";
  form.elements.telefono.value = v.telefono || "";
  form.elements.comision_porcentaje.value = v.comision_porcentaje || 0;
  form.elements.usuario_id.value = v.usuario_id || "";
  form.elements.activo.checked = Number(v.activo) === 1;
  document.getElementById("titVend").textContent = "Editar vendedor";
  bootstrap.Modal.getOrCreateInstance(document.getElementById("modalVend")).show();
});
document.getElementById("formVend").addEventListener("submit", function (ev) {
  ev.preventDefault();
  var body = crmForm("formVend");
  body.activo = document.getElementById("chkActivo").checked ? 1 : 0;
  var id = body.id;
  delete body.id;
  var opts = { method: id ? "PUT" : "POST", body: body };
  var url = id ? "api/vendedores.php?id="+encodeURIComponent(id) : "api/vendedores.php";
  crmApi(url, opts).then(function () {
    bootstrap.Modal.getInstance(document.getElementById("modalVend")).hide();
    loadVend();
    crmToast("Vendedor guardado");
  }).catch(function (e) { crmToast(e.message, true); });
});
loadVend();
</script>
<?php crm_layout_end(); ?>
