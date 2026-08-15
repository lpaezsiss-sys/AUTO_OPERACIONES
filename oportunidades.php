<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Oportunidades', 'oportunidades', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="page-title h3 mb-0">Pipeline de oportunidades</h1>
    <button class="btn" style="background:#fec001;color:#05294B;font-weight:700" data-bs-toggle="modal" data-bs-target="#modalOpp">Nueva oportunidad</button>
</div>
<div class="kanban" id="board"></div>
<div class="modal fade" id="modalOpp" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="formOpp">
      <div class="modal-header"><h5 class="modal-title">Nueva oportunidad</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
      <div class="modal-body row g-2">
        <div class="col-12"><label class="form-label">Empresa</label><select class="form-select" name="empresa_id" id="selEmpresa" required></select></div>
        <div class="col-12"><label class="form-label">Título</label><input class="form-control" name="titulo" required></div>
        <div class="col-6"><label class="form-label">Valor estimado CLP</label><input class="form-control" name="valor_estimado" type="number" min="0"></div>
        <div class="col-6"><label class="form-label">Probabilidad %</label><input class="form-control" name="probabilidad" type="number" min="0" max="100" value="20"></div>
        <div class="col-6"><label class="form-label">Etapa</label><select class="form-select" name="etapa" id="selEtapa"></select></div>
        <div class="col-6"><label class="form-label">Canal</label><select class="form-select" name="origen_canal" id="selCanal"></select></div>
        <div class="col-12"><label class="form-label">Cierre esperado</label><input class="form-control" name="fecha_cierre_esperada" type="date"></div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary" type="submit">Guardar</button></div>
    </form>
  </div>
</div>
<script>
var etapas = [];
var cache = [];
function render(rows) {
  cache = rows || [];
  var cols = etapas.map(function (et) {
    var items = cache.filter(function (o) { return o.etapa === et; });
    var cards = items.map(function (o) {
      return '<div class="kanban-card" data-id="'+o.id+'"><div class="small text-secondary">'+o.codigo+'</div><strong>'+o.titulo+'</strong><div class="small">'+o.razon_social+'</div><div class="small">'+crmClp(o.valor_estimado)+'</div><select class="form-select form-select-sm mt-2 etapa-sel" data-id="'+o.id+'">'+etapas.map(function (x){return '<option value="'+x+'"'+(x===o.etapa?' selected':'')+'>'+x+'</option>';}).join("")+'</select></div>';
    }).join("");
    return '<div class="kanban-col"><div class="fw-bold mb-2 text-capitalize">'+et+' <span class="badge text-bg-light">'+items.length+'</span></div>'+cards+'</div>';
  }).join("");
  document.getElementById("board").innerHTML = cols;
}
function load() {
  crmApi("api/oportunidades.php").then(function (d) { render(d.oportunidades); });
}
Promise.all([crmApi("api/catalogos.php"), crmApi("api/empresas.php")]).then(function (arr) {
  etapas = arr[0].etapas || [];
  document.getElementById("selEtapa").innerHTML = etapas.map(function (e){return '<option value="'+e+'">'+e+'</option>';}).join("");
  document.getElementById("selCanal").innerHTML = (arr[0].canales||[]).map(function (e){return '<option value="'+e+'">'+e+'</option>';}).join("");
  document.getElementById("selEmpresa").innerHTML = (arr[1].empresas||[]).map(function (e){return '<option value="'+e.id+'">'+e.razon_social+'</option>';}).join("");
  load();
});
document.getElementById("board").addEventListener("change", function (ev) {
  if (!ev.target.classList.contains("etapa-sel")) return;
  var id = ev.target.getAttribute("data-id");
  var opp = cache.filter(function (o) { return String(o.id) === String(id); })[0];
  if (!opp) return;
  var body = { empresa_id: opp.empresa_id, titulo: opp.titulo, etapa: ev.target.value, valor_estimado: opp.valor_estimado, probabilidad: opp.probabilidad, origen_canal: opp.origen_canal, fecha_cierre_esperada: opp.fecha_cierre_esperada, ejecutivo_id: opp.ejecutivo_id, contacto_id: opp.contacto_id, notas: opp.notas, motivo_perdida: opp.motivo_perdida };
  crmApi("api/oportunidades.php?id="+id, { method: "PUT", body: body })
    .then(load)
    .catch(function (e) { crmToast(e.message, true); });
});
document.getElementById("formOpp").addEventListener("submit", function (ev) {
  ev.preventDefault();
  crmApi("api/oportunidades.php", { method: "POST", body: crmForm("formOpp") })
    .then(function () { bootstrap.Modal.getInstance(document.getElementById("modalOpp")).hide(); load(); crmToast("Oportunidad creada"); })
    .catch(function (e) { crmToast(e.message, true); });
});
</script>
<?php crm_layout_end(); ?>
