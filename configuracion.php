<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Empresa emisora', 'configuracion', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="page-title h3 mb-1">Empresa emisora</h1>
        <p class="text-secondary mb-0">Datos de facturación y logo que identifican a LPAEZsis en cotizaciones.</p>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-8">
        <form class="card card-soft p-4" id="formConfig">
            <div class="row g-2">
                <div class="col-md-4"><label class="form-label">RUT</label><input class="form-control" name="rut" required></div>
                <div class="col-md-8"><label class="form-label">Razón social</label><input class="form-control" name="razon_social" required></div>
                <div class="col-md-6"><label class="form-label">Nombre de fantasía</label><input class="form-control" name="nombre_fantasia"></div>
                <div class="col-md-6"><label class="form-label">Giro</label><input class="form-control" name="giro"></div>
                <div class="col-12"><label class="form-label">Dirección</label><input class="form-control" name="direccion" required></div>
                <div class="col-md-4"><label class="form-label">Ciudad</label><input class="form-control" name="ciudad"></div>
                <div class="col-md-4"><label class="form-label">Región</label><input class="form-control" name="region"></div>
                <div class="col-md-4"><label class="form-label">Teléfono</label><input class="form-control" name="telefono"></div>
                <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email" type="email"></div>
                <div class="col-md-6"><label class="form-label">Sitio web</label><input class="form-control" name="sitio_web"></div>
            </div>
            <button class="btn mt-3" type="submit" style="background:#fec001;color:#05294B;font-weight:700">Guardar configuración</button>
        </form>
    </div>
    <div class="col-lg-4">
        <div class="card card-soft p-4">
            <h2 class="h6">Logo</h2>
            <img id="logoPreview" alt="Logo empresa" class="img-fluid mb-3 border rounded bg-white p-2" style="max-height:140px;display:none">
            <div class="small text-secondary mb-2" id="logoPath"></div>
            <form id="formLogo">
                <input class="form-control" type="file" name="logo" accept="image/png,image/jpeg" required>
                <button class="btn btn-outline-primary mt-2 w-100" type="submit">Subir logo PNG/JPG</button>
            </form>
            <?php if ((string) $user['rol'] === 'admin') { ?>
            <hr>
            <h2 class="h6">Respaldo del CRM</h2>
            <p class="small text-secondary">Genera un ZIP con código, dump SQL y uploads (sin .env ni .git).</p>
            <button class="btn btn-outline-secondary w-100" type="button" id="btnRespaldo">Descargar Respaldo ZIP</button>
            <div class="small text-secondary mt-2" id="respaldoInfo"></div>
            <?php } ?>
        </div>
    </div>
</div>
<script>
(function () {
  function fill(cfg) {
    var form = document.getElementById("formConfig");
    ["rut","razon_social","nombre_fantasia","giro","direccion","ciudad","region","telefono","email","sitio_web"].forEach(function (k) {
      if (form.elements[k]) form.elements[k].value = cfg[k] || "";
    });
    var img = document.getElementById("logoPreview");
    var path = cfg.logo_path || "";
    document.getElementById("logoPath").textContent = path || "Sin logo cargado";
    if (path) {
      img.src = path + "?t=" + Date.now();
      img.style.display = "block";
    }
  }
  crmApi("api/configuracion.php").then(function (d) { fill(d.configuracion || {}); })
    .catch(function (e) { crmToast(e.message, true); });
  document.getElementById("formConfig").addEventListener("submit", function (ev) {
    ev.preventDefault();
    var fd = new FormData(ev.currentTarget);
    fetch("api/configuracion.php", { method: "POST", credentials: "same-origin", body: fd })
      .then(function (r) { return r.json().then(function (d) { return { r: r, d: d }; }); })
      .then(function (pack) {
        if (!pack.r.ok || pack.d.ok === false) throw new Error(pack.d.error || "No se pudo guardar");
        fill(pack.d.configuracion || {});
        crmToast("Configuración guardada");
      })
      .catch(function (e) { crmToast(e.message, true); });
  });
  document.getElementById("formLogo").addEventListener("submit", function (ev) {
    ev.preventDefault();
    var fd = new FormData(ev.currentTarget);
    fetch("api/configuracion.php", { method: "POST", credentials: "same-origin", body: fd })
      .then(function (r) { return r.json().then(function (d) { return { r: r, d: d }; }); })
      .then(function (pack) {
        if (!pack.r.ok || pack.d.ok === false) throw new Error(pack.d.error || "No se pudo subir");
        fill(pack.d.configuracion || {});
        crmToast("Logo actualizado");
      })
      .catch(function (e) { crmToast(e.message, true); });
  });
  var btnR = document.getElementById("btnRespaldo");
  if (btnR) {
    btnR.addEventListener("click", function () {
      btnR.disabled = true;
      btnR.textContent = "Generando…";
      crmApi("api/respaldo.php?action=generar", { method: "POST", body: { action: "generar" } })
        .then(function (d) {
          var info = document.getElementById("respaldoInfo");
          info.textContent = (d.archivo || "") + " · " + (d.mb || 0) + " MB"
            + (d.incluye_sql ? " · incluye dump SQL" : "");
          crmToast("Respaldo listo");
          window.location.href = d.ruta || ("downloads/" + d.archivo);
        })
        .catch(function (e) { crmToast(e.message, true); })
        .then(function () {
          btnR.disabled = false;
          btnR.textContent = "Descargar Respaldo ZIP";
        });
    });
  }
})();
</script>
<?php crm_layout_end(); ?>
