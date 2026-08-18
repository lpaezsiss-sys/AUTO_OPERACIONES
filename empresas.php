<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Empresas', 'empresas', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="page-title h3 mb-0">Empresas</h1>
    <button class="btn" style="background:#fec001;color:#05294B;font-weight:700" id="btnNuevaEmp">Nueva empresa</button>
</div>
<div class="card card-soft p-3">
    <div class="row g-2 mb-3">
        <div class="col-md-6"><input id="q" class="form-control" placeholder="Buscar razón social, RUT o email"></div>
        <div class="col-md-3">
            <select id="estado" class="form-select">
                <option value="">Todos los estados</option>
                <option value="prospecto">Prospecto</option>
                <option value="activa">Activa</option>
                <option value="inactiva">Inactiva</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>RUT</th><th>Razón social</th><th>Industria</th><th>Origen</th><th>Estado</th><th></th></tr></thead>
            <tbody id="rows"></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalEmpresa" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="formEmpresa">
      <div class="modal-header"><h5 class="modal-title" id="titEmpresa">Nueva empresa</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
      <div class="modal-body row g-2">
        <input type="hidden" name="id" id="empresaId">
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
var empresasCache = [];
function fillSelect(id, arr, first) {
  var el = document.getElementById(id);
  el.innerHTML = (first ? '<option value="">'+first+'</option>' : '') + (arr||[]).map(function (v) {
    return '<option value="'+v+'">'+v+'</option>';
  }).join("");
}
function resetEmpresaForm() {
  document.getElementById("formEmpresa").reset();
  document.getElementById("empresaId").value = "";
  document.getElementById("titEmpresa").textContent = "Nueva empresa";
}
function fillEmpresaForm(e) {
  var form = document.getElementById("formEmpresa");
  document.getElementById("empresaId").value = e.id;
  ["rut","razon_social","nombre_fantasia","giro","industria","region","comuna","direccion","telefono","email","sitio_web","origen","estado","notas"].forEach(function (k) {
    if (form.elements[k]) form.elements[k].value = e[k] || "";
  });
  if (form.elements.lista_precio_id) form.elements.lista_precio_id.value = e.lista_precio_id || "";
  document.getElementById("titEmpresa").textContent = "Editar empresa";
}
function loadEmpresas() {
  var q = document.getElementById("q").value;
  var estado = document.getElementById("estado").value;
  crmApi("api/empresas.php?q="+encodeURIComponent(q)+"&estado="+encodeURIComponent(estado)).then(function (d) {
    empresasCache = d.empresas || [];
    document.getElementById("rows").innerHTML = empresasCache.map(function (e) {
      return '<tr><td>'+e.rut+'</td><td><a href="empresa.php?id='+e.id+'">'+e.razon_social+'</a></td><td>'+(e.industria||"")+'</td><td>'+e.origen+'</td><td>'+e.estado+'</td>' +
        '<td><button class="btn btn-sm btn-outline-primary" type="button" data-edit="'+e.id+'">Editar</button></td></tr>';
    }).join("");
  }).catch(function (e) { crmToast(e.message, true); });
}
crmApi("api/catalogos.php").then(function (c) {
  fillSelect("selIndustria", c.industrias, "Seleccione");
  fillSelect("selRegion", c.regiones, "Seleccione");
  fillSelect("selOrigen", c.origenes);
});
crmApi("api/listas_precios.php").then(function (d) {
  var sel = document.getElementById("selListaPrecio");
  sel.innerHTML = '<option value="">(predeterminada del sistema)</option>' + (d.listas || []).filter(function (l) {
    return l.estado === "activa";
  }).map(function (l) {
    var tag = Number(l.es_default) === 1 ? " · default" : "";
    return '<option value="'+l.id+'">'+l.nombre+' ('+(Number(l.porcentaje_ajuste)>0?'+':'')+Number(l.porcentaje_ajuste).toFixed(2)+'%)'+tag+'</option>';
  }).join("");
}).catch(function (e) { crmToast(e.message, true); });
["q","estado"].forEach(function (id) {
  document.getElementById(id).addEventListener("input", loadEmpresas);
  document.getElementById(id).addEventListener("change", loadEmpresas);
});
document.getElementById("btnNuevaEmp").addEventListener("click", function () {
  resetEmpresaForm();
  bootstrap.Modal.getOrCreateInstance(document.getElementById("modalEmpresa")).show();
});
document.getElementById("rows").addEventListener("click", function (ev) {
  var id = ev.target.getAttribute("data-edit");
  if (!id) return;
  var e = empresasCache.filter(function (x) { return String(x.id) === String(id); })[0];
  if (!e) return;
  fillEmpresaForm(e);
  bootstrap.Modal.getOrCreateInstance(document.getElementById("modalEmpresa")).show();
});
document.getElementById("formEmpresa").addEventListener("submit", function (ev) {
  ev.preventDefault();
  var body = crmForm("formEmpresa");
  var id = document.getElementById("empresaId").value;
  var method = id ? "PUT" : "POST";
  var url = id ? "api/empresas.php?id="+id : "api/empresas.php";
  crmApi(url, { method: method, body: body })
    .then(function (d) {
      if (!id) { window.location.href = "empresa.php?id="+d.empresa.id; return; }
      bootstrap.Modal.getInstance(document.getElementById("modalEmpresa")).hide();
      crmToast("Empresa actualizada");
      loadEmpresas();
    })
    .catch(function (e) { crmToast(e.message, true); });
});
loadEmpresas();
</script>
<?php crm_layout_end(); ?>
