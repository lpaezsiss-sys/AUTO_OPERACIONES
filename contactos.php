<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Contactos', 'contactos', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="page-title h3 mb-0">Contactos</h1>
    <button class="btn" style="background:#fec001;color:#05294B;font-weight:700" id="btnNuevoCto">Nuevo contacto</button>
</div>
<div class="card card-soft p-3">
    <input id="q" class="form-control mb-3" placeholder="Buscar nombre, email o WhatsApp">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Nombre</th><th>Empresa</th><th>Cargo</th><th>Teléfono</th><th>Email</th><th></th></tr></thead>
            <tbody id="rows"></tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="modalCto" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="formCto">
      <div class="modal-header"><h5 class="modal-title" id="titCto">Nuevo contacto</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
      <div class="modal-body row g-2">
        <input type="hidden" name="id" id="ctoId">
        <div class="col-12"><label class="form-label">Empresa</label><select class="form-select" name="empresa_id" id="selEmpresa" required></select></div>
        <div class="col-6"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>
        <div class="col-6"><label class="form-label">Apellido</label><input class="form-control" name="apellido"></div>
        <div class="col-12"><label class="form-label">Cargo</label><input class="form-control" name="cargo"></div>
        <div class="col-6"><label class="form-label">Email</label><input class="form-control" name="email" type="email"></div>
        <div class="col-6"><label class="form-label">Teléfono</label><input class="form-control" name="telefono"></div>
        <div class="col-6"><label class="form-label">WhatsApp</label><input class="form-control" name="whatsapp"></div>
        <div class="col-6"><label class="form-label">Canal preferido</label><select class="form-select" name="canal_preferido" id="selCanal"></select></div>
        <div class="col-12 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="es_principal" id="esPrincipal"><label class="form-check-label" for="esPrincipal">Principal</label></div></div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary" type="submit">Guardar</button></div>
    </form>
  </div>
</div>
<script>
var contactosCache = [];
function load() {
  crmApi("api/contactos.php?q="+encodeURIComponent(document.getElementById("q").value)).then(function (d) {
    contactosCache = d.contactos || [];
    document.getElementById("rows").innerHTML = contactosCache.map(function (c) {
      return '<tr><td>'+c.nombre+' '+(c.apellido||"")+'</td><td><a href="empresa.php?id='+c.empresa_id+'">'+c.razon_social+'</a></td><td>'+(c.cargo||"")+'</td><td>'+(c.telefono||"")+'</td><td>'+(c.email||"")+'</td>' +
        '<td class="text-nowrap"><button class="btn btn-sm btn-outline-primary me-1" type="button" data-edit="'+c.id+'">Editar</button>' +
        '<button class="btn btn-sm btn-outline-danger" type="button" data-del="'+c.id+'">Eliminar</button></td></tr>';
    }).join("");
  });
}
function resetCto() {
  document.getElementById("formCto").reset();
  document.getElementById("ctoId").value = "";
  document.getElementById("titCto").textContent = "Nuevo contacto";
  document.getElementById("esPrincipal").checked = false;
}
Promise.all([crmApi("api/empresas.php"), crmApi("api/catalogos.php")]).then(function (arr) {
  document.getElementById("selEmpresa").innerHTML = (arr[0].empresas||[]).map(function (e) {
    return '<option value="'+e.id+'">'+e.razon_social+'</option>';
  }).join("");
  document.getElementById("selCanal").innerHTML = (arr[1].canales||[]).map(function (c) {
    return '<option value="'+c+'">'+c+'</option>';
  }).join("");
});
document.getElementById("q").addEventListener("input", load);
document.getElementById("btnNuevoCto").addEventListener("click", function () {
  resetCto();
  bootstrap.Modal.getOrCreateInstance(document.getElementById("modalCto")).show();
});
document.getElementById("rows").addEventListener("click", function (ev) {
  var edit = ev.target.getAttribute("data-edit");
  var del = ev.target.getAttribute("data-del");
  if (edit) {
    var c = contactosCache.filter(function (x) { return String(x.id) === String(edit); })[0];
    if (!c) return;
    document.getElementById("ctoId").value = c.id;
    var form = document.getElementById("formCto");
    ["empresa_id","nombre","apellido","cargo","email","telefono","whatsapp","canal_preferido"].forEach(function (k) {
      if (form.elements[k]) form.elements[k].value = c[k] || "";
    });
    document.getElementById("esPrincipal").checked = Number(c.es_principal) === 1;
    document.getElementById("titCto").textContent = "Editar contacto";
    bootstrap.Modal.getOrCreateInstance(document.getElementById("modalCto")).show();
  }
  if (del) {
    if (!window.confirm("¿Eliminar este contacto? No se podrá si tiene cotizaciones asociadas.")) return;
    crmApi("api/contactos.php?id="+del, { method: "DELETE" })
      .then(function () { crmToast("Contacto eliminado"); load(); })
      .catch(function (e) { crmToast(e.message, true); });
  }
});
document.getElementById("formCto").addEventListener("submit", function (ev) {
  ev.preventDefault();
  var body = crmForm("formCto");
  body.es_principal = document.getElementById("esPrincipal").checked;
  var id = document.getElementById("ctoId").value;
  var method = id ? "PUT" : "POST";
  var url = id ? "api/contactos.php?id="+id : "api/contactos.php";
  crmApi(url, { method: method, body: body })
    .then(function () {
      bootstrap.Modal.getInstance(document.getElementById("modalCto")).hide();
      load();
      crmToast(id ? "Contacto actualizado" : "Contacto creado");
    })
    .catch(function (e) { crmToast(e.message, true); });
});
load();
</script>
<?php crm_layout_end(); ?>
