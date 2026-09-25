(function () {
  "use strict";

  var salir = document.getElementById("btnSalir");
  if (salir && salir.tagName === "BUTTON") {
    salir.addEventListener("click", function () {
      crmApi("api/usuarios.php?action=logout", { method: "POST", body: { action: "logout" } })
        .then(function () {
          crmToast("Sesión cerrada");
          window.location.href = "login.php";
        })
        .catch(function (e) { crmToast(e.message, true); });
    });
  }
})();
