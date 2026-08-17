<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: empresas.php');
    exit;
}
crm_layout_start('Ficha empresa', 'empresas', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="empresas.php" class="text-decoration-none">← Empresas</a>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary" type="button" id="btnEditar">Editar</button>
        <button class="btn btn-sm btn-outline-danger" type="button" id="btnEliminar">Eliminar</button>
    </div>
</div>
<div id="ficha"></div>
<div class="modal fade" id="modalEmpresa" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="formEmpresa">
      <div class="modal-header"><h5 class="modal-title">Editar empresa</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
      <div class="modal-body row g-2">
        <div class="col-md-4"><label class="form-label">RUT</label><input class="form-control" name="rut" required></div>
        <div class="col-md-8"><label class="form-label">Razón social</label><input class="form-control" name="razon_social" required></div>
        <div class="col-md-6"><label class="form-label">Nombre de fantasía</label><input class="form-control" name="nombre_fantasia"></div>
        <div class="col-md-6"><label class="form-label">Giro</label><input class="form-control" name="giro"></div>
        <div class="col-md-4"><label class="form-label">Industria</label><select class="form-select" name="industria" id="selIndustria"></select></div>
        <div class="col-md-4"><label class="form-label">Región</label><select class="form-select" name="region" id="selRegion"></select></div>
        <div class="col-md-4"><label class="form-label">Comuna</label><input class="form-control" name="comuna"></div>
        <div class="col-12"><label class="form-label">Dirección</label><input class="form-control" name="direccion"></div>
        <div class="col-md-6"><label class="form-label">Teléfono</label><input class="form-control" name="telefono"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email" type="email"></div>
        <div class="col-md-6"><label class="form-label">Sitio web</label><input class="form-control" name="sitio_web"></div>
        <div class="col-md-3"><label class="form-label">Origen</label><select class="form-select" name="origen" id="selOrigen"></select></div>
        <div class="col-md-3"><label class="form-label">Estado</label>
            <select class="form-select" name="estado">
                <option value="prospecto">Prospecto</option>
                <option value="activa">Activa</option>
                <option value="inactiva">Inactiva</option>
            </select>
        </div>
        <div class="col-12"><label class="form-label">Notas</label><textarea class="form-control" name="notas" rows="2"></textarea></div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary" type="submit">Guardar</button></div>
    </form>
  </div>
</div>
<script>
var id = <?php echo (int) $id; ?>;
var fichaData = null;
function fillSelect(elId, arr, first) {
  var el = document.getElementById(elId);
  el.innerHTML = (first ? '<option value="">'+first+'</option>' : '') + (arr||[]).map(function (v) {
    return '<option value="'+v+'">'+v+'</option>';
  }).join("");
}
function renderFicha(d) {
  var e = d.empresa;
  var html = '';
  html += '<div class="card card-soft p-4 mb-3"><h1 class="h4 page-title">'+e.razon_social+'</h1>';
  html += '<div class="text-secondary">'+e.rut+' · '+(e.industria||"")+' · '+(e.region||"")+'</div>';
  html += '<div class="mt-2">'+(e.direccion||"")+(e.comuna ? ' · '+e.comuna : '')+'</div>';
  html += '<div class="mt-2">Origen: <strong>'+e.origen+'</strong> · Estado: <strong>'+e.estado+'</strong></div></div>';
  html += '<div class="row g-3">';
  html += '<div class="col-lg-4"><div class="card card-soft p-3"><h2 class="h6">Contactos</h2>'+(d.contactos||[]).map(function (c) {
    return '<div class="border-bottom py-2"><strong>'+c.nombre+' '+(c.apellido||"")+'</strong><div class="small text-secondary">'+(c.cargo||"")+' · '+(c.telefono||c.whatsapp||c.email||"")+'</div></div>';
  }).join("")+'</div></div>';
  html += '<div class="col-lg-4"><div class="card card-soft p-3"><h2 class="h6">Oportunidades</h2>'+(d.oportunidades||[]).map(function (o) {
    return '<div class="border-bottom py-2"><div>'+o.codigo+' · '+o.titulo+'</div><div class="small">'+o.etapa+' · '+crmClp(o.valor_estimado)+'</div></div>';
  }).join("")+'</div></div>';
  html += '<div class="col-lg-4"><div class="card card-soft p-3"><h2 class="h6">Cotizaciones</h2>'+(d.cotizaciones||[]).map(function (c) {
    return '<div class="border-bottom py-2"><a href="cotizacion.php?id='+c.id+'">'+c.folio+'</a> · '+c.estado+'<div class="small">'+crmClp(c.total)+'</div></div>';
  }).join("")+'</div></div></div>';
  html += '<div class="card card-soft p-3 mt-3"><h2 class="h6">Línea de tiempo omnicanal</h2>'+(d.actividades||[]).map(function (a) {
    return '<div class="border-bottom py-2"><strong>'+a.titulo+'</strong> <span class="badge text-bg-light">'+a.canal+'</span><div class="small text-secondary">'+a.tipo+' · '+a.estado+'</div></div>';
  }).join("")+'</div>';
  document.getElementById("ficha").innerHTML = html;
}
function loadFicha() {
  return crmApi("api/empresas.php?id="+id).then(function (d) {
    fichaData = d;
    renderFicha(d);
    return d;
  }).catch(function (e) { crmToast(e.message, true); });
}
Promise.all([loadFicha(), crmApi("api/catalogos.php")]).then(function (arr) {
  var c = arr[1] || {};
  fillSelect("selIndustria", c.industrias, "Seleccione");
  fillSelect("selRegion", c.regiones, "Seleccione");
  fillSelect("selOrigen", c.origenes);
});
document.getElementById("btnEditar").addEventListener("click", function () {
  if (!fichaData) return;
  var e = fichaData.empresa;
  var form = document.getElementById("formEmpresa");
  ["rut","razon_social","nombre_fantasia","giro","industria","region","comuna","direccion","telefono","email","sitio_web","origen","estado","notas"].forEach(function (k) {
    if (form.elements[k]) form.elements[k].value = e[k] || "";
  });
  bootstrap.Modal.getOrCreateInstance(document.getElementById("modalEmpresa")).show();
});
document.getElementById("formEmpresa").addEventListener("submit", function (ev) {
  ev.preventDefault();
  crmApi("api/empresas.php?id="+id, { method: "PUT", body: crmForm("formEmpresa") })
    .then(function (d) {
      bootstrap.Modal.getInstance(document.getElementById("modalEmpresa")).hide();
      fichaData = d;
      renderFicha(d);
      crmToast("Empresa actualizada");
    })
    .catch(function (e) { crmToast(e.message, true); });
});
document.getElementById("btnEliminar").addEventListener("click", function () {
  if (!window.confirm("¿Eliminar esta empresa? No se podrá si tiene cotizaciones asociadas.")) return;
  crmApi("api/empresas.php?id="+id, { method: "DELETE" })
    .then(function () { crmToast("Empresa eliminada"); window.location.href = "empresas.php"; })
    .catch(function (e) { crmToast(e.message, true); });
});
</script>
<?php crm_layout_end(); ?>
