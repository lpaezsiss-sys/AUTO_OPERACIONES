<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Omnicanal', 'actividades', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="page-title h3 mb-0">Bandeja omnicanal</h1>
    <button class="btn" style="background:#fec001;color:#05294B;font-weight:700" data-bs-toggle="modal" data-bs-target="#modalAct">Nueva actividad</button>
</div>
<div class="card card-soft p-3 mb-3">
    <div class="row g-2">
        <div class="col-md-4">
            <select id="canal" class="form-select">
                <option value="">Todos los canales</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="email">Email</option>
                <option value="telefono">Teléfono</option>
                <option value="web">Web</option>
                <option value="linkedin">LinkedIn</option>
                <option value="visita">Visita</option>
                <option value="feria">Feria</option>
            </select>
        </div>
        <div class="col-md-4">
            <select id="estado" class="form-select">
                <option value="">Todos los estados</option>
                <option value="pendiente">Pendiente</option>
                <option value="completada">Completada</option>
                <option value="cancelada">Cancelada</option>
            </select>
        </div>
    </div>
</div>
<div class="card card-soft p-3" id="list"></div>
<div class="modal fade" id="modalAct" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="formAct">
      <div class="modal-header"><h5 class="modal-title">Nueva actividad</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
      <div class="modal-body row g-2">
        <div class="col-12"><label class="form-label">Empresa</label><select class="form-select" name="empresa_id" id="selEmpresa"></select></div>
        <div class="col-12"><label class="form-label">Título</label><input class="form-control" name="titulo" required></div>
        <div class="col-6"><label class="form-label">Tipo</label><select class="form-select" name="tipo" id="selTipo"></select></div>
        <div class="col-6"><label class="form-label">Canal</label><select class="form-select" name="canal" id="selCanal"></select></div>
        <div class="col-12"><label class="form-label">Programada</label><input class="form-control" type="datetime-local" name="fecha_programada"></div>
        <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion" rows="2"></textarea></div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary" type="submit">Guardar</button></div>
    </form>
  </div>
</div>
<script>
function load() {
  var canal = document.getElementById("canal").value;
  var estado = document.getElementById("estado").value;
  crmApi("api/actividades.php?canal="+encodeURIComponent(canal)+"&estado="+encodeURIComponent(estado)).then(function (d) {
    document.getElementById("list").innerHTML = (d.actividades||[]).map(function (a) {
      return '<div class="d-flex justify-content-between border-bottom py-2"><div><strong>'+a.titulo+'</strong><div class="small text-secondary">'+(a.razon_social||"")+' · '+a.tipo+' · '+a.canal+'</div></div><span class="badge text-bg-light">'+a.estado+'</span></div>';
    }).join("") || '<div class="text-secondary">Sin actividades.</div>';
  });
}
Promise.all([crmApi("api/empresas.php"), crmApi("api/catalogos.php")]).then(function (arr) {
  document.getElementById("selEmpresa").innerHTML = '<option value="">(opcional)</option>'+(arr[0].empresas||[]).map(function (e){return '<option value="'+e.id+'">'+e.razon_social+'</option>';}).join("");
  document.getElementById("selTipo").innerHTML = (arr[1].actividad_tipos||[]).map(function (e){return '<option value="'+e+'">'+e+'</option>';}).join("");
  document.getElementById("selCanal").innerHTML = (arr[1].canales||[]).map(function (e){return '<option value="'+e+'">'+e+'</option>';}).join("");
});
["canal","estado"].forEach(function (id) { document.getElementById(id).addEventListener("change", load); });
document.getElementById("formAct").addEventListener("submit", function (ev) {
  ev.preventDefault();
  var body = crmForm("formAct");
  if (body.fecha_programada) body.fecha_programada = body.fecha_programada.replace("T", " ");
  crmApi("api/actividades.php", { method: "POST", body: body })
    .then(function () { bootstrap.Modal.getInstance(document.getElementById("modalAct")).hide(); load(); crmToast("Actividad registrada"); })
    .catch(function (e) { crmToast(e.message, true); });
});
load();
</script>
<?php crm_layout_end(); ?>
