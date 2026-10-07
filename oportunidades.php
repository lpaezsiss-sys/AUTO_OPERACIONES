<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Oportunidades', 'oportunidades', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="page-title h3 mb-0">Pipeline de oportunidades</h1>
    <div class="d-flex align-items-center gap-3">
        <label class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="fMias">
            <span class="form-check-label">Mis oportunidades</span>
        </label>
        <button class="btn" style="background:#fec001;color:#05294B;font-weight:700" data-bs-toggle="modal" data-bs-target="#modalOpp">Nueva oportunidad</button>
    </div>
</div>
<div class="kanban" id="board"></div>
<div class="offcanvas offcanvas-end" tabindex="-1" id="drawerOpp">
  <div class="offcanvas-header">
    <h2 class="offcanvas-title h5 mb-0" id="drawerTitle">Oportunidad</h2>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body" id="drawerBody"></div>
</div>
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
<div class="modal fade" id="modalPerdida" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="formPerdida">
      <div class="modal-header"><h5 class="modal-title">Motivo de pérdida</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
      <div class="modal-body">
        <input type="hidden" id="perdidaId" value="">
        <label class="form-label" for="perdidaMotivo">¿Por qué se pierde? (obligatorio)</label>
        <textarea class="form-control" id="perdidaMotivo" rows="3" required placeholder="Precio, timing, competencia, presupuesto…"></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit">Guardar pérdida</button>
      </div>
    </form>
  </div>
</div>
<script>
var etapas = [];
var etapaEtiquetas = {};
var cache = [];
var drawer = null;
function etiquetaEtapa(et) {
  return etapaEtiquetas[et] || et;
}
function optionsEtapa(selected) {
  return etapas.map(function (x) {
    return '<option value="'+x+'"'+(x===selected?' selected':'')+'>'+crmEsc(etiquetaEtapa(x))+'</option>';
  }).join("");
}
function bodyOpp(opp, extra) {
  extra = extra || {};
  return {
    empresa_id: opp.empresa_id,
    titulo: opp.titulo,
    etapa: extra.etapa != null ? extra.etapa : opp.etapa,
    valor_estimado: opp.valor_estimado,
    probabilidad: opp.probabilidad,
    origen_canal: opp.origen_canal,
    fecha_cierre_esperada: opp.fecha_cierre_esperada,
    ejecutivo_id: opp.ejecutivo_id,
    contacto_id: opp.contacto_id,
    notas: extra.notas != null ? extra.notas : opp.notas,
    motivo_perdida: extra.motivo_perdida != null ? extra.motivo_perdida : opp.motivo_perdida
  };
}
function render(rows) {
  cache = rows || [];
  var cols = etapas.map(function (et) {
    var items = cache.filter(function (o) { return o.etapa === et; });
    var cards = items.map(function (o) {
      var cotHref = "cotizador.php?empresa_id="+o.empresa_id+"&oportunidad_id="+o.id+(o.contacto_id ? "&contacto_id="+o.contacto_id : "");
      return '<div class="kanban-card" data-id="'+o.id+'">' +
        '<div class="small text-secondary">'+crmEsc(o.codigo)+'</div>' +
        '<strong>'+crmEsc(o.titulo)+'</strong>' +
        '<div class="small">'+crmEsc(o.razon_social)+'</div>' +
        '<div class="small">'+crmClp(o.valor_estimado)+' · '+crmEsc(o.probabilidad||0)+'%</div>' +
        '<div class="small text-secondary">'+crmEsc(o.ejecutivo_nombre||"")+'</div>' +
        '<a class="btn btn-sm btn-outline-primary mt-2 btn-cotizar-opp" href="'+cotHref+'">Cotizar</a>' +
        '<select class="form-select form-select-sm mt-2 etapa-sel" data-id="'+o.id+'">'+optionsEtapa(o.etapa)+'</select></div>';
    }).join("");
    return '<div class="kanban-col"><div class="fw-bold mb-2">'+crmEsc(etiquetaEtapa(et))+' <span class="badge text-bg-light">'+items.length+'</span></div>'+cards+'</div>';
  }).join("");
  document.getElementById("board").innerHTML = cols;
}
function loadUrl() {
  var mias = document.getElementById("fMias").checked ? "1" : "";
  var url = "api/oportunidades.php";
  if (mias) url += "?mias=1";
  return url;
}
function load() {
  return crmApi(loadUrl()).then(function (d) { render(d.oportunidades); });
}
function pedirMotivo(id) {
  document.getElementById("perdidaId").value = String(id);
  document.getElementById("perdidaMotivo").value = "";
  bootstrap.Modal.getOrCreateInstance(document.getElementById("modalPerdida")).show();
}
function guardarEtapa(id, etapa, motivo) {
  var opp = cache.filter(function (o) { return String(o.id) === String(id); })[0];
  if (!opp) return Promise.resolve();
  var extra = { etapa: etapa };
  if (motivo) extra.motivo_perdida = motivo;
  return crmApi("api/oportunidades.php?id="+id, { method: "PUT", body: bodyOpp(opp, extra) })
    .then(function () { load(); if (drawer && drawer._openId === String(id)) openDrawer(id); })
    .catch(function (e) { crmToast(e.message, true); load(); });
}
function openDrawer(id) {
  crmApi("api/oportunidades.php?id="+id).then(function (d) {
    var o = d.oportunidad || {};
    var idx = -1;
    cache.forEach(function (row, i) { if (String(row.id) === String(o.id)) idx = i; });
    if (idx >= 0) cache[idx] = o;
    else cache.push(o);
    var cotHref = "cotizador.php?empresa_id="+o.empresa_id+"&oportunidad_id="+o.id+(o.contacto_id ? "&contacto_id="+o.contacto_id : "");
    var prox = o.proxima_actividad;
    var html = '';
    html += '<div class="crm-drawer-meta mb-2">'+crmEsc(o.codigo)+' · '+crmEsc(o.razon_social||"")+'</div>';
    html += '<div class="mb-2">'+crmClp(o.valor_estimado)+' · Prob. '+crmEsc(o.probabilidad||0)+'% · '+crmEsc(o.ejecutivo_nombre||"")+'</div>';
    html += '<label class="form-label">Etapa</label>';
    html += '<select class="form-select mb-3 etapa-sel" data-id="'+o.id+'">'+optionsEtapa(o.etapa)+'</select>';
    if (o.etapa === "perdida" && o.motivo_perdida) {
      html += '<div class="alert alert-light border small">Motivo: '+crmEsc(o.motivo_perdida)+'</div>';
    }
    html += '<label class="form-label">Notas</label>';
    html += '<textarea class="form-control mb-3" id="drawerNotas" rows="3">'+crmEsc(o.notas||"")+'</textarea>';
    html += '<div class="mb-3"><div class="fw-bold small">Próxima actividad</div>';
    html += prox
      ? '<div>'+crmEsc(prox.titulo)+'</div><div class="small text-secondary">'+crmEsc(prox.tipo)+' · '+crmEsc(prox.fecha_programada||"sin fecha")+'</div>'
      : '<div class="small text-secondary">Sin seguimiento pendiente.</div>';
    html += '</div>';
    html += '<a class="btn w-100 mb-2" style="background:#fec001;color:#05294B;font-weight:700" href="'+cotHref+'">Crear cotización</a>';
    html += '<button class="btn btn-outline-primary w-100" type="button" id="btnGuardarNotas">Guardar notas</button>';
    document.getElementById("drawerTitle").textContent = o.titulo || "Oportunidad";
    document.getElementById("drawerBody").innerHTML = html;
    document.getElementById("btnGuardarNotas").addEventListener("click", function () {
      crmApi("api/oportunidades.php?id="+o.id, { method: "PUT", body: bodyOpp(o, { notas: document.getElementById("drawerNotas").value }) })
        .then(function () { crmToast("Notas guardadas"); load(); })
        .catch(function (e) { crmToast(e.message, true); });
    });
    drawer = bootstrap.Offcanvas.getOrCreateInstance(document.getElementById("drawerOpp"));
    drawer._openId = String(o.id);
    drawer.show();
  }).catch(function (e) { crmToast(e.message, true); });
}
var params = new URLSearchParams(window.location.search);
var defaultMias = params.get("mias");
if (defaultMias === null) defaultMias = window.crmRol === "vendedor" ? "1" : "";
document.getElementById("fMias").checked = defaultMias === "1";
Promise.all([crmApi("api/catalogos.php"), crmApi("api/empresas.php")]).then(function (arr) {
  etapas = arr[0].etapas || [];
  etapaEtiquetas = arr[0].etapa_etiquetas || {};
  document.getElementById("selEtapa").innerHTML = optionsEtapa("prospecto");
  document.getElementById("selCanal").innerHTML = (arr[0].canales||[]).map(function (e){return '<option value="'+e+'">'+e+'</option>';}).join("");
  document.getElementById("selEmpresa").innerHTML = (arr[1].empresas||[]).map(function (e){return '<option value="'+e.id+'">'+e.razon_social+'</option>';}).join("");
  load();
});
document.getElementById("fMias").addEventListener("change", function () {
  var q = new URLSearchParams(window.location.search);
  if (this.checked) q.set("mias", "1"); else q.delete("mias");
  var next = q.toString();
  window.history.replaceState({}, "", "oportunidades.php"+(next ? "?"+next : ""));
  load();
});
document.getElementById("board").addEventListener("click", function (ev) {
  if (ev.target.closest("select") || ev.target.closest("a") || ev.target.closest("button")) return;
  var card = ev.target.closest(".kanban-card");
  if (!card) return;
  openDrawer(card.getAttribute("data-id"));
});
document.addEventListener("change", function (ev) {
  if (!ev.target.classList.contains("etapa-sel")) return;
  var id = ev.target.getAttribute("data-id");
  var etapa = ev.target.value;
  if (etapa === "perdida") {
    ev.target.value = (cache.filter(function (o) { return String(o.id) === String(id); })[0] || {}).etapa || "";
    pedirMotivo(id);
    return;
  }
  guardarEtapa(id, etapa);
});
document.getElementById("formPerdida").addEventListener("submit", function (ev) {
  ev.preventDefault();
  var id = document.getElementById("perdidaId").value;
  var motivo = document.getElementById("perdidaMotivo").value.replace(/^\s+|\s+$/g, "");
  if (!motivo) {
    crmToast("Indique el motivo de pérdida", true);
    return;
  }
  bootstrap.Modal.getInstance(document.getElementById("modalPerdida")).hide();
  guardarEtapa(id, "perdida", motivo);
});
document.getElementById("formOpp").addEventListener("submit", function (ev) {
  ev.preventDefault();
  crmApi("api/oportunidades.php", { method: "POST", body: crmForm("formOpp") })
    .then(function () { bootstrap.Modal.getInstance(document.getElementById("modalOpp")).hide(); load(); crmToast("Oportunidad creada"); })
    .catch(function (e) { crmToast(e.message, true); });
});
</script>
<?php crm_layout_end(); ?>
