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
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm" style="background:#fec001;color:#05294B;font-weight:700" id="btnCotizar" href="cotizador.php">Cotizar</a>
        <a class="btn btn-sm btn-outline-success" id="btnWhatsapp" target="_blank" rel="noopener" hidden>WhatsApp</a>
        <button class="btn btn-sm btn-outline-primary" type="button" id="btnEditar">Editar</button>
        <button class="btn btn-sm btn-outline-secondary" type="button" id="btnEliminar">Eliminar</button>
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
        <div class="col-md-6"><label class="form-label">Lista de precios</label>
            <select class="form-select" name="lista_precio_id" id="selListaPrecio">
                <option value="">(predeterminada del sistema)</option>
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
function emptyBox(msg) {
  return '<div class="text-secondary small py-2">'+crmEsc(msg)+'</div>';
}
function renderFicha(d) {
  var e = d.empresa;
  var skus = d.skus || [];
  var html = '';
  html += '<div class="card card-soft p-4 mb-3"><h1 class="h4 page-title mb-1">'+crmEsc(e.razon_social)+'</h1>';
  html += '<div class="text-secondary">'+crmEsc(e.rut)+' · <span class="text-capitalize">'+crmEsc(e.estado||"")+'</span> · Lista: <strong>'+crmEsc(e.lista_precio_nombre || "Predeterminada del sistema")+'</strong></div>';
  html += '<div class="small mt-2">'+crmEsc(e.industria||"")+(e.region ? ' · '+crmEsc(e.region) : '')+(e.direccion ? ' · '+crmEsc(e.direccion) : '')+(e.comuna ? ' · '+crmEsc(e.comuna) : '')+'</div></div>';
  html += '<ul class="nav nav-tabs ficha-tabs mb-3" id="fichaTabs">';
  html += '<li class="nav-item"><button class="nav-link active" type="button" data-tab="resumen">Resumen</button></li>';
  html += '<li class="nav-item"><button class="nav-link" type="button" data-tab="skus">Equipos / SKU</button></li>';
  html += '<li class="nav-item"><button class="nav-link" type="button" data-tab="cots">Cotizaciones</button></li>';
  html += '<li class="nav-item"><button class="nav-link" type="button" data-tab="act">Actividad</button></li>';
  html += '</ul>';
  html += '<div class="tab-pane-crm" data-pane="resumen">';
  html += '<div class="row g-3">';
  html += '<div class="col-lg-6"><div class="card card-soft p-3"><h2 class="h6">Contactos</h2>'+((d.contactos||[]).length ? (d.contactos||[]).map(function (c) {
    var wa = window.crmWhatsAppUrl(c.whatsapp || c.telefono);
    var waLink = wa ? ' · <a href="'+wa+'" target="_blank" rel="noopener">WhatsApp</a>' : '';
    return '<div class="border-bottom py-2"><strong>'+crmEsc(c.nombre)+' '+crmEsc(c.apellido||"")+'</strong><div class="small text-secondary">'+crmEsc(c.cargo||"")+' · '+crmEsc(c.telefono||c.whatsapp||c.email||"")+waLink+'</div></div>';
  }).join("") : emptyBox("Sin contactos"))+'</div></div>';
  html += '<div class="col-lg-6"><div class="card card-soft p-3"><h2 class="h6">Oportunidades</h2>'+((d.oportunidades||[]).length ? (d.oportunidades||[]).map(function (o) {
    return '<div class="border-bottom py-2"><div>'+crmEsc(o.codigo)+' · '+crmEsc(o.titulo)+'</div><div class="small">'+crmEsc(o.etapa)+' · '+crmClp(o.valor_estimado)+' · <a href="cotizador.php?empresa_id='+id+'&oportunidad_id='+o.id+'">Cotizar</a></div></div>';
  }).join("") : emptyBox("Sin oportunidades"))+'</div></div></div></div>';
  html += '<div class="tab-pane-crm" data-pane="skus" hidden>';
  html += '<div class="card card-soft p-3"><h2 class="h6">Últimos SKU y precios</h2>';
  if (!skus.length) {
    html += emptyBox("Aún no hay SKU cotizados (se omiten rechazadas y vencidas).");
  } else {
    html += '<div class="table-responsive"><table class="table align-middle"><thead><tr><th>SKU</th><th>Descripción</th><th class="text-end">Último precio</th><th>Fecha</th><th>Folio</th><th>Veces</th></tr></thead><tbody>';
    html += skus.map(function (s) {
      return '<tr><td>'+crmEsc(s.codigo)+'</td><td>'+crmEsc(s.descripcion)+'<div class="small text-secondary">'+crmEsc(s.badge||"")+'</div></td><td class="text-end">'+crmClp(s.precio_unitario)+'</td><td>'+crmEsc(s.fecha_emision||"")+'</td><td><a href="cotizacion.php?id='+s.cotizacion_id+'">'+crmEsc(s.folio)+'</a></td><td>'+crmEsc(s.veces)+'</td></tr>';
    }).join("");
    html += '</tbody></table></div>';
  }
  html += '</div></div>';
  html += '<div class="tab-pane-crm" data-pane="cots" hidden><div class="card card-soft p-3"><h2 class="h6">Cotizaciones</h2>'+((d.cotizaciones||[]).length ? (d.cotizaciones||[]).map(function (c) {
    return '<div class="border-bottom py-2"><a href="cotizacion.php?id='+c.id+'">'+crmEsc(c.folio)+'</a> · '+crmEsc(c.estado)+'<div class="small">'+crmClp(c.total)+'</div></div>';
  }).join("") : emptyBox("Sin cotizaciones"))+'</div></div>';
  html += '<div class="tab-pane-crm" data-pane="act" hidden><div class="card card-soft p-3"><h2 class="h6">Actividad</h2>'+((d.actividades||[]).length ? (d.actividades||[]).map(function (a) {
    return '<div class="border-bottom py-2"><strong>'+crmEsc(a.titulo)+'</strong> <span class="badge text-bg-light">'+crmEsc(a.canal)+'</span><div class="small text-secondary">'+crmEsc(a.tipo)+' · '+crmEsc(a.estado)+'</div></div>';
  }).join("") : emptyBox("Sin actividad"))+'</div></div>';
  document.getElementById("ficha").innerHTML = html;
}
document.getElementById("ficha").addEventListener("click", function (ev) {
  var btn = ev.target.closest("[data-tab]");
  if (!btn) return;
  var tab = btn.getAttribute("data-tab");
  document.querySelectorAll("#fichaTabs .nav-link").forEach(function (el) {
    el.classList.toggle("active", el.getAttribute("data-tab") === tab);
  });
  document.querySelectorAll("#ficha .tab-pane-crm").forEach(function (el) {
    el.hidden = el.getAttribute("data-pane") !== tab;
  });
});
function loadFicha() {
  return crmApi("api/empresas.php?id="+id).then(function (d) {
    fichaData = d;
    renderFicha(d);
    var cot = document.getElementById("btnCotizar");
    if (cot) cot.href = "cotizador.php?empresa_id="+id;
    var waBtn = document.getElementById("btnWhatsapp");
    var contactos = d.contactos || [];
    var principal = null;
    contactos.forEach(function (c) {
      if (!principal && Number(c.es_principal) === 1) principal = c;
    });
    if (!principal && contactos.length) principal = contactos[0];
    var wa = principal ? window.crmWhatsAppUrl(principal.whatsapp || principal.telefono) : "";
    if (waBtn) {
      if (wa) {
        waBtn.href = wa;
        waBtn.hidden = false;
      } else {
        waBtn.hidden = true;
      }
    }
    return d;
  }).catch(function (e) { crmToast(e.message, true); });
}
Promise.all([loadFicha(), crmApi("api/catalogos.php"), crmApi("api/listas_precios.php")]).then(function (arr) {
  var c = arr[1] || {};
  fillSelect("selIndustria", c.industrias, "Seleccione");
  fillSelect("selRegion", c.regiones, "Seleccione");
  fillSelect("selOrigen", c.origenes);
  var sel = document.getElementById("selListaPrecio");
  sel.innerHTML = '<option value="">(predeterminada del sistema)</option>' + ((arr[2] && arr[2].listas) || []).map(function (l) {
    var tag = Number(l.es_default) === 1 ? " · default" : "";
    return '<option value="'+l.id+'">'+l.nombre+' ('+(Number(l.porcentaje_ajuste)>0?'+':'')+Number(l.porcentaje_ajuste).toFixed(2)+'%)'+tag+'</option>';
  }).join("");
});
document.getElementById("btnEditar").addEventListener("click", function () {
  if (!fichaData) return;
  var e = fichaData.empresa;
  var form = document.getElementById("formEmpresa");
  ["rut","razon_social","nombre_fantasia","giro","industria","region","comuna","direccion","telefono","email","sitio_web","origen","estado","notas"].forEach(function (k) {
    if (form.elements[k]) form.elements[k].value = e[k] || "";
  });
  if (form.elements.lista_precio_id) form.elements.lista_precio_id.value = e.lista_precio_id || "";
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
