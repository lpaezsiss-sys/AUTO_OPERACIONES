<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$next = trim((string) ($_GET['next'] ?? 'index.php'));
if ($next === '' || str_contains($next, '..') || str_contains($next, '://') || !str_ends_with(strtolower($next), '.php')) {
    $next = 'index.php';
}
$next = ltrim(str_replace('\\', '/', $next), '/');

if (isset($_GET['logout'])) {
    \Crm\Comex\Usuarios::cerrarSesion();
    header('Location: login.php');
    http_response_code(302);
    exit;
}

if (\Auth::isLoggedIn()) {
    header('Location: ' . $next);
    http_response_code(302);
    exit;
}

$appName = (string) crm_env('APP_NAME', 'COMEX LPAEZsis');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · <?php echo crm_h($appName); ?></title>
    <link rel="icon" href="assets/img/logo.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</head>
<body class="page-login">
<div id="crmToast" class="toast align-items-center text-bg-dark border-0" role="status">
    <div class="d-flex">
        <div class="toast-body" id="crmToastBody"></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
</div>
<div class="login-shell">
    <div class="login-brand">
        <img src="assets/img/logo.svg" alt="LPAEZsis" width="48" height="48">
        <div>
            <div class="login-kicker">LPAEZSIS</div>
            <h1 class="h4 mb-0">COMEX</h1>
        </div>
    </div>
    <div class="card login-card">
        <h2 class="h5 mb-1" style="color:#05294B">Iniciar sesión</h2>
        <p class="small text-secondary mb-3">Acceso obligatorio al panel COMEX. Roles <code>admin</code> y <code>comex</code>.</p>
        <form id="formLogin" data-next="<?php echo crm_h($next); ?>">
            <div class="mb-2">
                <label class="form-label" for="loginEmail">Email</label>
                <input id="loginEmail" name="email" type="email" class="form-control" required autocomplete="username" placeholder="admin@comex.lpaezsis.cl">
            </div>
            <div class="mb-3">
                <label class="form-label" for="loginPassword">Contraseña</label>
                <input id="loginPassword" name="password" type="password" class="form-control" required autocomplete="current-password">
            </div>
            <button class="btn btn-yellow w-100" type="submit">Entrar</button>
        </form>
        <p class="small text-secondary mt-3 mb-0">Pruebas: <code>admin@comex.lpaezsis.cl</code> o <code>comex@comex.lpaezsis.cl</code> · <code>Comex2026!</code></p>
    </div>
</div>
<script src="assets/js/login.js"></script>
</body>
</html>
