<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Marcas', 'marcas', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="page-title h3 mb-1">Marcas representadas</h1>
        <p class="text-secondary mb-0">Logos que aparecen en el pie de las cotizaciones. Las marcadas como globales se usan si la cotización no selecciona marcas.</p>
    </div>
    <button class="btn" style="background:#fec001;color:#05294B;font-weight:700" data-bs-toggle="modal" data-bs-target="#modalMarca" id="btnNueva">Nueva marca</button>
</div>
<div class="card card-soft p-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Logo</th>
                    <th>Nombre</th>
                    <th>Archivo</th>
                    <th>Orden</th>
                    <th>Activa</th>
                    <th>Global (PDF)</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="rows"></tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="modalMarca" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="formMarca">
      <div class="modal-header"><h5 class="modal-title" id="titMarca">Nueva marca</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
      <div class="modal-body row g-2">
        <input type="hidden" name="id" id="marcaId">
        <div class="col-12"><label class="form-label">Nombre</label><input class="form-control" name="nombre" required></div>
        <div class="col-md-6"><label class="form-label">Orden</label><input class="form-control" name="orden" type="number" value="0"></div>
        <div class="col-12"><label class="form-label">Logo (PNG, JPG o SVG)</label><input class="form-control" type="file" name="archivo" id="marcaArchivo" accept="image/png,image/jpeg,image/svg+xml"></div>
        <div class="col-12"><img id="marcaPreview" alt="" class="img-fluid border rounded bg-white p-2" style="max-height:80px;display:none"></div>
        <div class="col-6 form-check mt-2">
            <input class="form-check-input" type="checkbox" name="activa" id="chkActiva" checked>
            <label class="form-check-label" for="chkActiva">Activa</label>
        </div>
        <div class="col-6 form-check mt-2">
            <input class="form-check-input" type="checkbox" name="incluir_global" id="chkGlobal" checked>
            <label class="form-check-label" for="chkGlobal">Incluir en PDF global</label>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary" type="submit">Guardar</button></div>
    </form>
  </div>
</div>
<script>
var marcas = [];
function loadMarcas() {
  crmApi("api/marcas.php").then(function (d) {
    marcas = d.marcas || [];
    document.getElementById("rows").innerHTML = marcas.map(function (m) {
      var img = m.existe_archivo ? '<img src="'+m.url+'?t='+Date.now()+'" alt="" style="max-height:36px;max-width:90px">' : '—';
      return '<tr>' +
        '<td>'+img+'</td>' +
        '<td>'+m.nombre+'</td>' +
        '<td class="small">'+m.archivo+'</td>' +
        '<td>'+m.orden+'</td>' +
        '<td>'+(Number(m.activa)===1?'Sí':'No')+'</td>' +
        '<td>'+(Number(m.incluir_global)===1?'Sí':'No')+'</td>' +
        '<td class="text-nowrap">' +
          '<button class="btn btn-sm btn-outline-primary me-1" type="button" data-edit="'+m.id+'">Editar</button>' +
          '<button class="btn btn-sm btn-outline-danger" type="button" data-del="'+m.id+'">Eliminar</button>' +
        '</td></tr>';
    }).join("");
  }).catch(function (e) { crmToast(e.message, true); });
}
document.getElementById("btnNueva").addEventListener("click", function () {
  document.getElementById("formMarca").reset();
  document.getElementById("marcaId").value = "";
  document.getElementById("chkActiva").checked = true;
  document.getElementById("chkGlobal").checked = true;
  document.getElementById("titMarca").textContent = "Nueva marca";
  document.getElementById("marcaArchivo").required = true;
  document.getElementById("marcaPreview").style.display = "none";
});
document.getElementById("rows").addEventListener("click", function (ev) {
  var edit = ev.target.getAttribute("data-edit");
  var del = ev.target.getAttribute("data-del");
  if (edit) {
    var m = marcas.filter(function (x) { return String(x.id) === String(edit); })[0];
    if (!m) return;
    document.getElementById("marcaId").value = m.id;
    document.querySelector('#formMarca [name=nombre]').value = m.nombre;
    document.querySelector('#formMarca [name=orden]').value = m.orden;
    document.getElementById("chkActiva").checked = Number(m.activa) === 1;
    document.getElementById("chkGlobal").checked = Number(m.incluir_global) === 1;
    document.getElementById("marcaArchivo").value = "";
    document.getElementById("marcaArchivo").required = false;
    document.getElementById("titMarca").textContent = "Editar marca";
    var prev = document.getElementById("marcaPreview");
    if (m.url) { prev.src = m.url; prev.style.display = "block"; } else { prev.style.display = "none"; }
    bootstrap.Modal.getOrCreateInstance(document.getElementById("modalMarca")).show();
  }
  if (del) {
    if (!window.confirm("¿Eliminar esta marca? Se quitará de las cotizaciones que la tengan seleccionada.")) return;
    crmApi("api/marcas.php?id="+del, { method: "DELETE" })
      .then(function () { crmToast("Marca eliminada"); loadMarcas(); })
      .catch(function (e) { crmToast(e.message, true); });
  }
});
document.getElementById("formMarca").addEventListener("submit", function (ev) {
  ev.preventDefault();
  var id = document.getElementById("marcaId").value;
  var fd = new FormData(ev.currentTarget);
  fd.set("activa", document.getElementById("chkActiva").checked ? "1" : "0");
  fd.set("incluir_global", document.getElementById("chkGlobal").checked ? "1" : "0");
  if (!fd.get("archivo") || !fd.get("archivo").name) {
    fd.delete("archivo");
  }
  var url = id ? "api/marcas.php?id="+id : "api/marcas.php";
  crmApi(url, { method: "POST", body: fd })
    .then(function () {
      bootstrap.Modal.getInstance(document.getElementById("modalMarca")).hide();
      crmToast("Marca guardada");
      loadMarcas();
    })
    .catch(function (e) { crmToast(e.message, true); });
});
loadMarcas();
</script>
<?php crm_layout_end(); ?>
