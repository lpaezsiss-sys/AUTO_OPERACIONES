<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (\Crm\Auth::user() !== null) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · CRM LPAEZsis</title>
    <link rel="icon" href="assets/img/logo.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="login-wrap">
    <form class="login-card" id="loginForm">
        <div class="d-flex align-items-center gap-2 mb-3">
            <img src="assets/img/logo.svg" width="40" height="40" alt="LPAEZsis">
            <div>
                <div class="fw-bold" style="color:#05294B">CRM Industrial Omnicanal</div>
                <div class="small text-secondary">LPAEZsis · crm.lpaezsis.cl</div>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="email" required autocomplete="username">
        </div>
        <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <input class="form-control" type="password" name="password" required autocomplete="current-password">
        </div>
        <div class="text-danger small mb-3" id="loginError" hidden></div>
        <button class="btn w-100" type="submit" style="background:#fec001;color:#05294B;font-weight:700">Ingresar</button>
    </form>
</div>
<script src="assets/js/app.js"></script>
<script>
document.getElementById("loginForm").addEventListener("submit", function (ev) {
  ev.preventDefault();
  var err = document.getElementById("loginError");
  err.hidden = true;
  var body = crmForm("loginForm");
  if (body.email) { body.email = String(body.email).replace(/^\s+|\s+$/g, ""); }
  if (body.password) { body.password = String(body.password).replace(/^\s+|\s+$/g, ""); }
  crmApi("api/auth.php", { method: "POST", body: body })
    .then(function () { window.location.href = "index.php"; })
    .catch(function (e) {
      err.textContent = e.message;
      err.hidden = false;
    });
});
</script>
</body>
</html>
