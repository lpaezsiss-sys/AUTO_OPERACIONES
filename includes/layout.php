<?php

declare(strict_types=1);

/**
 * @param string $title
 * @param string $page
 * @param array $user
 * @return void
 */
function crm_layout_start($title, $page, array $user)
{
    $title = (string) $title;
    $page = (string) $page;
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo crm_h($title); ?> · CRM LPAEZsis</title>
    <link rel="icon" href="assets/img/logo.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar">
        <a class="brand" href="index.php">
            <img src="assets/img/logo.svg" alt="LPAEZsis" width="36" height="36">
            <span>CRM LPAEZsis</span>
        </a>
        <nav class="nav flex-column">
            <a class="nav-link<?php echo $page === 'dashboard' ? ' active' : ''; ?>" href="index.php">Dashboard</a>
            <a class="nav-link<?php echo $page === 'empresas' ? ' active' : ''; ?>" href="empresas.php">Empresas</a>
            <a class="nav-link<?php echo $page === 'contactos' ? ' active' : ''; ?>" href="contactos.php">Contactos</a>
            <a class="nav-link<?php echo $page === 'oportunidades' ? ' active' : ''; ?>" href="oportunidades.php">Oportunidades</a>
            <a class="nav-link<?php echo $page === 'cotizaciones' ? ' active' : ''; ?>" href="cotizaciones.php">Cotizaciones</a>
            <a class="nav-link<?php echo $page === 'cotizador' ? ' active' : ''; ?>" href="cotizador.php">Cotizador</a>
            <a class="nav-link<?php echo $page === 'vendedores' ? ' active' : ''; ?>" href="vendedores.php">Vendedores</a>
            <a class="nav-link<?php echo $page === 'comisiones' ? ' active' : ''; ?>" href="comisiones.php">Comisiones</a>
            <a class="nav-link<?php echo $page === 'actividades' ? ' active' : ''; ?>" href="actividades.php">Omnicanal</a>
            <a class="nav-link<?php echo $page === 'productos' ? ' active' : ''; ?>" href="productos.php">Inventario</a>
            <a class="nav-link<?php echo $page === 'configuracion' ? ' active' : ''; ?>" href="configuracion.php">Empresa</a>
        </nav>
        <div class="sidebar-user">
            <div class="small text-uppercase opacity-75">Sesión</div>
            <div><?php echo crm_h($user['nombre']); ?></div>
            <div class="small opacity-75"><?php echo crm_h($user['email']); ?></div>
            <button class="btn btn-sm btn-outline-warning mt-2 w-100" type="button" id="btnLogout">Salir</button>
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

function crm_layout_end()
{
    ?>
    </main>
</div>
</body>
</html>
    <?php
}
