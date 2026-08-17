<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_require_admin();
crm_layout_start('Usuarios', 'usuarios', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="page-title h3 mb-1">Usuarios</h1>
        <p class="text-secondary mb-0">Cuentas de acceso al CRM. Solo administradores pueden crear o editar.</p>
    </div>
    <button class="btn" style="background:#fec001;color:#05294B;font-weight:700" data-bs-toggle="modal" data-bs-target="#modalUser" id="btnNuevo">Nuevo usuario</button>
</div>
<div class="card card-soft p-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol / Perfil</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="rows"></tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="modalUser" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="formUser">
      <div class="modal-header"><h5 class="modal-title" id="titUser">Nuevo usuario</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
      <div class="modal-body row g-2">
        <input type="hidden" name="id" id="userId">
        <div class="col-12"><label class="form-label">Nombre completo</label><input class="form-control" name="nombre" required></div>
        <div class="col-12"><label class="form-label">Correo electrónico (login)</label><input class="form-control" name="email" type="email" required></div>
        <div class="col-12">
            <label class="form-label" id="lblPass">Contraseña</label>
            <input class="form-control" name="password" type="password" autocomplete="new-password" minlength="8">
            <div class="form-text" id="hintPass">Mínimo 8 caracteres.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Rol / Perfil</label>
            <select class="form-select" name="rol">
                <option value="vendedor">Vendedor</option>
                <option value="admin">Administrador</option>
            </select>
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="activo" id="chkActivo" checked>
                <label class="form-check-label" for="chkActivo">Activo</label>
            </div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary" type="submit">Guardar</button></div>
    </form>
  </div>
</div>
<script>
var usuarios = [];
function rolLabel(rol) {
  return rol === "admin" ? "Administrador" : "Vendedor";
}
function loadUsers() {
  crmApi("api/usuarios.php").then(function (d) {
    usuarios = d.usuarios || [];
    document.getElementById("rows").innerHTML = usuarios.map(function (u) {
      var activo = Number(u.activo) === 1;
      return "<tr>" +
        "<td>" + crmEsc(u.nombre) + "</td>" +
        "<td>" + crmEsc(u.email) + "</td>" +
        "<td>" + rolLabel(u.rol) + "</td>" +
        "<td>" + (activo ? "Activo" : "Inactivo") + "</td>" +
        '<td><button class="btn btn-sm btn-outline-primary" type="button" data-edit="' + u.id + '">Editar</button></td>' +
        "</tr>";
    }).join("");
  }).catch(function (e) { crmToast(e.message, true); });
}
function modoNuevo() {
  document.getElementById("formUser").reset();
  document.getElementById("userId").value = "";
  document.getElementById("chkActivo").checked = true;
  document.getElementById("titUser").textContent = "Nuevo usuario";
  document.getElementById("lblPass").textContent = "Contraseña";
  document.getElementById("hintPass").textContent = "Mínimo 8 caracteres.";
  document.querySelector("#formUser [name=password]").required = true;
}
document.getElementById("btnNuevo").addEventListener("click", modoNuevo);
document.getElementById("rows").addEventListener("click", function (ev) {
  var raw = ev.target.getAttribute("data-edit");
  if (!raw) return;
  var u = null;
  usuarios.forEach(function (row) { if (String(row.id) === String(raw)) u = row; });
  if (!u) return;
  var form = document.getElementById("formUser");
  form.elements.id.value = u.id;
  form.elements.nombre.value = u.nombre || "";
  form.elements.email.value = u.email || "";
  form.elements.rol.value = u.rol || "vendedor";
  form.elements.password.value = "";
  form.elements.password.required = false;
  form.elements.activo.checked = Number(u.activo) === 1;
  document.getElementById("titUser").textContent = "Editar usuario";
  document.getElementById("lblPass").textContent = "Nueva contraseña (opcional)";
  document.getElementById("hintPass").textContent = "Dejar en blanco para no cambiar. Mínimo 8 caracteres si se cambia.";
  bootstrap.Modal.getOrCreateInstance(document.getElementById("modalUser")).show();
});
document.getElementById("formUser").addEventListener("submit", function (ev) {
  ev.preventDefault();
  var body = crmForm("formUser");
  body.activo = document.getElementById("chkActivo").checked ? 1 : 0;
  var id = body.id;
  delete body.id;
  if (!body.password) {
    delete body.password;
  }
  var opts = { method: id ? "PUT" : "POST", body: body };
  var url = id ? "api/usuarios.php?id=" + encodeURIComponent(id) : "api/usuarios.php";
  crmApi(url, opts).then(function () {
    bootstrap.Modal.getInstance(document.getElementById("modalUser")).hide();
    loadUsers();
    crmToast("Usuario guardado");
  }).catch(function (e) { crmToast(e.message, true); });
});
loadUsers();
</script>
<?php crm_layout_end(); ?>
