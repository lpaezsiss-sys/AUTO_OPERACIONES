<?php

declare(strict_types=1);

/**
 * Shell visual alineado al CRM LPAEZsis (navy / yellow, sidebar, Bootstrap 5).
 *
 * @param array<string, mixed> $user
 */
function crm_layout_start(string $title, string $page, array $user = []): void
{
    $appName = (string) crm_env('APP_NAME', 'COMEX LPAEZsis');
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo crm_h($title); ?> · <?php echo crm_h($appName); ?></title>
    <link rel="icon" href="assets/img/logo.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</head>
<body>
<button class="btn btn-nav-toggle d-lg-none" type="button" id="btnNav" aria-label="Menú">☰</button>
<div class="app-shell">
    <aside class="app-sidebar" id="appSidebar">
        <a class="brand" href="index.php">
            <img src="assets/img/logo.svg" alt="LPAEZsis" width="36" height="36">
            <span>COMEX LPAEZsis</span>
        </a>
        <nav class="nav flex-column">
            <a class="nav-link<?php echo $page === 'dashboard' ? ' active' : ''; ?>" href="index.php">Dashboard</a>
            <a class="nav-link<?php echo $page === 'landed' ? ' active' : ''; ?>" href="landed.php">Landed cost</a>
            <a class="nav-link<?php echo $page === 'operaciones' ? ' active' : ''; ?>" href="operaciones.php">Operaciones</a>
            <a class="nav-link<?php echo $page === 'fichas' ? ' active' : ''; ?>" href="fichas.php">Fichas</a>
            <a class="nav-link" href="https://crm.lpaezsis.cl" target="_blank" rel="noopener">CRM</a>
            <a class="nav-link" href="https://inventario.lpaezsis.cl" target="_blank" rel="noopener">Inventario</a>
        </nav>
        <div class="sidebar-user">
            <div class="small text-uppercase opacity-75">Ecosistema</div>
            <div><?php echo crm_h($user['nombre'] ?? 'COMEX Chile'); ?></div>
            <div class="small opacity-75">IVA aduanero <?php echo crm_h((string) crm_iva_pct()); ?>%</div>
        </div>
    </aside>
    <main class="app-main">
        <div id="crmToast" class="toast align-items-center text-bg-dark border-0" role="status">
            <div class="d-flex">
                <div class="toast-body" id="crmToastBody"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    <?php
}

function crm_layout_end(): void
{
    ?>
    </main>
</div>
<script>
document.getElementById("btnNav")?.addEventListener("click", function () {
  document.getElementById("appSidebar")?.classList.toggle("open");
});
</script>
</body>
</html>
    <?php
}
