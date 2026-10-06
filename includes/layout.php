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
    $isAdmin = (string) $user['rol'] === 'admin';
    $cssV = (int) @filemtime(__DIR__ . '/../assets/css/app.css');
    $jsV = (int) @filemtime(__DIR__ . '/../assets/js/app.js');
    $link = static function ($key, $href, $label, $page, $muted = false) {
        $cls = 'nav-link';
        if ($page === $key) {
            $cls .= ' active';
        }
        if ($muted) {
            $cls .= ' nav-link-muted';
        }
        echo '<a class="' . $cls . '" href="' . $href . '">' . $label . '</a>' . "\n";
    };
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo crm_h($title); ?> · CRM LPAEZsis</title>
    <link rel="icon" href="assets/img/logo.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/app.css?v=<?php echo $cssV; ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js?v=<?php echo $jsV; ?>"></script>
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar">
        <a class="brand" href="index.php">
            <img src="assets/img/logo.svg" alt="LPAEZsis" width="36" height="36">
            <span>CRM LPAEZsis</span>
        </a>
        <nav class="nav flex-column">
            <div class="nav-group-label">Hoy</div>
            <?php $link('dashboard', 'index.php', 'Dashboard', $page); ?>
            <?php $link('actividades', 'actividades.php', 'Agenda', $page); ?>

            <div class="nav-group-label">Vender</div>
            <?php $link('cotizador', 'cotizador.php', 'Cotizador', $page); ?>
            <?php $link('cotizaciones', 'cotizaciones.php', 'Cotizaciones', $page); ?>
            <?php $link('oportunidades', 'oportunidades.php', 'Oportunidades', $page); ?>

            <div class="nav-group-label">Clientes</div>
            <?php $link('empresas', 'empresas.php', 'Empresas', $page); ?>
            <?php $link('contactos', 'contactos.php', 'Contactos', $page, true); ?>

            <div class="nav-group-label">Catálogo</div>
            <?php $link('productos', 'productos.php', 'Inventario', $page); ?>
            <?php $link('estadisticas_a_pedido', 'estadisticas_a_pedido.php', 'Estadísticas a pedido', $page); ?>

            <div class="nav-group-label">Gestión</div>
            <?php $link('vendedores', 'vendedores.php', 'Vendedores', $page); ?>
            <?php $link('comisiones', 'comisiones.php', 'Comisiones', $page); ?>
            <?php $link('reportes', 'reportes.php', 'Informes', $page); ?>
            <?php if ($isAdmin) { ?>
            <?php $link('usuarios', 'usuarios.php', 'Usuarios', $page); ?>
            <?php $link('listas_precios', 'listas_precios.php', 'Listas de precios', $page); ?>
            <?php } ?>
            <?php $link('configuracion', 'configuracion.php', 'Empresa', $page); ?>
            <?php $link('marcas', 'marcas.php', 'Marcas', $page, true); ?>
            <?php $link('manual', 'manual.php', 'Manual', $page, true); ?>
        </nav>
        <div class="sidebar-user">
            <div class="small text-uppercase opacity-75">Sesión</div>
            <div><?php echo crm_h($user['nombre']); ?></div>
            <div class="small opacity-75"><?php echo crm_h($user['email']); ?></div>
            <button class="btn btn-sm btn-outline-warning mt-2 w-100" type="button" id="btnLogout">Salir</button>
        </div>
    </aside>
    <main class="app-main">
        <script>window.crmRol = <?php echo json_encode((string) $user['rol']); ?>;</script>
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
