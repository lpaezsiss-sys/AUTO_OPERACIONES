(function () {
  "use strict";

  var form = document.getElementById("formLoginCrm");
  if (form) {
    form.addEventListener("submit", function (ev) {
      ev.preventDefault();
      crmApi("api/usuarios.php?action=login", {
        method: "POST",
        body: {
          action: "login",
          email: document.getElementById("loginEmail").value.trim(),
          password: document.getElementById("loginPassword").value,
        },
      }).then(function (d) {
        var u = d.usuario || {};
        crmToast("Sesión " + (u.rol_etiqueta || u.rol || ""));
        location.reload();
      }).catch(function (e) { crmToast(e.message, true); });
    });
  }

  var salir = document.getElementById("btnSalir");
  if (salir) {
    salir.addEventListener("click", function () {
      crmApi("api/usuarios.php?action=logout", { method: "POST", body: { action: "logout" } })
        .then(function () {
          crmToast("Sesión cerrada");
          location.reload();
        })
        .catch(function (e) { crmToast(e.message, true); });
    });
  }
})();
