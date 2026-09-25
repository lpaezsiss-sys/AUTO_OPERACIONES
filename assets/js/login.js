(function () {
  "use strict";

  var form = document.getElementById("formLogin");
  if (!form) {
    return;
  }
  form.addEventListener("submit", function (ev) {
    ev.preventDefault();
    var next = form.getAttribute("data-next") || "index.php";
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
      window.location.href = next;
    }).catch(function (e) {
      crmToast(e.message, true);
    });
  });
})();
