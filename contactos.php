<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Contactos', 'contactos', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="page-title h3 mb-0">Contactos</h1>
    <button class="btn" style="background:#fec001;color:#05294B;font-weight:700" data-bs-toggle="modal" data-bs-target="#modalCto">Nuevo contacto</button>
</div>
<div class="card card-soft p-3">
    <input id="q" class="form-control mb-3" placeholder="Buscar nombre, email o WhatsApp">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Nombre</th><th>Empresa</th><th>Canal</th><th>WhatsApp</th><th>Email</th></tr></thead>
            <tbody id="rows"></tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="modalCto" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="formCto">
      <div class="modal-header"><h5 class="modal-title">Nuevo contacto</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
      <div class="modal-body row g-2">
        <div class="col-12"><label class="form-label">Empresa</label><select class="form-select" name="empresa_id" id="selEmpresa" required></select></div>
        <div class="col-6"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>
        <div class="col-6"><label class="form-label">Apellido</label><input class="form-control" name="apellido"></div>
        <div class="col-12"><label class="form-label">Cargo</label><input class="form-control" name="cargo"></div>
        <div class="col-6"><label class="form-label">Email</label><input class="form-control" name="email" type="email"></div>
        <div class="col-6"><label class="form-label">WhatsApp</label><input class="form-control" name="whatsapp"></div>
        <div class="col-8"><label class="form-label">Canal preferido</label><select class="form-select" name="canal_preferido" id="selCanal"></select></div>
        <div class="col-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="es_principal" id="esPrincipal"><label class="form-check-label" for="esPrincipal">Principal</label></div></div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary" type="submit">Guardar</button></div>
    </form>
  </div>
</div>
<script>
function load() {
  crmApi("api/contactos.php?q="+encodeURIComponent(document.getElementById("q").value)).then(function (d) {
    document.getElementById("rows").innerHTML = (d.contactos||[]).map(function (c) {
      return '<tr><td>'+c.nombre+' '+(c.apellido||"")+'</td><td><a href="empresa.php?id='+c.empresa_id+'">'+c.razon_social+'</a></td><td>'+c.canal_preferido+'</td><td>'+(c.whatsapp||"")+'</td><td>'+(c.email||"")+'</td></tr>';
    }).join("");
  });
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
document.getElementById("formCto").addEventListener("submit", function (ev) {
  ev.preventDefault();
  var body = crmForm("formCto");
  body.es_principal = document.getElementById("esPrincipal").checked;
  crmApi("api/contactos.php", { method: "POST", body: body })
    .then(function () { bootstrap.Modal.getInstance(document.getElementById("modalCto")).hide(); load(); crmToast("Contacto creado"); })
    .catch(function (e) { crmToast(e.message, true); });
});
load();
</script>
<?php crm_layout_end(); ?>
