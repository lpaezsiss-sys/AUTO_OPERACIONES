<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$health = \Crm\Comex\Health::payload();
$appName = (string) crm_env('APP_NAME', 'COMEX LPAEZsis');
$inv = $health['inventory'];
$uploads = $health['uploads'];
$phpBadge = $health['php_is_81'] ? 'PHP 8.1 OK' : ('PHP ' . $health['php']);
$invBadge = !empty($inv['connected']) ? 'Inventario conectado' : 'Inventario pendiente';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo crm_h($appName); ?></title>
    <style>
        :root { color-scheme: light; }
        body { font-family: system-ui, sans-serif; margin: 0; background: #f4f6f8; color: #05294B; }
        header { background: #05294B; color: #fff; padding: 1.5rem 2rem; }
        header p { margin: .35rem 0 0; opacity: .85; }
        main { max-width: 880px; margin: 0 auto; padding: 1.5rem; }
        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .card { background: #fff; border-radius: 12px; padding: 1rem 1.1rem; box-shadow: 0 1px 4px rgb(5 41 75 / 8%); }
        .ok { color: #0f7b3a; font-weight: 600; }
        .warn { color: #b45309; font-weight: 600; }
        a { color: #0a5ea8; }
        code { font-size: .9em; background: #eef2f6; padding: .1em .35em; border-radius: 4px; }
        ul { margin: .4rem 0 0; padding-left: 1.1rem; }
    </style>
</head>
<body>
<header>
    <h1><?php echo crm_h($appName); ?></h1>
    <p>Ecosistema LPAEZSIS — crm.lpaezsis.cl · inventario.lpaezsis.cl · COMEX</p>
</header>
<main>
    <div class="grid">
        <div class="card">
            <div><?php echo $health['php_ok'] ? '<span class="ok">' . crm_h($phpBadge) . '</span>' : '<span class="warn">PHP incompatible</span>'; ?></div>
            <p>Runtime <?php echo crm_h((string) $health['php']); ?> · compat <?php echo crm_h((string) $health['compat']); ?></p>
        </div>
        <div class="card">
            <div><?php echo !empty($inv['connected']) ? '<span class="ok">' . crm_h($invBadge) . '</span>' : '<span class="warn">' . crm_h($invBadge) . '</span>'; ?></div>
            <p>SQLite inventario <code><?php echo crm_h((string) ($inv['path'] ?: 'INV_SQLITE_PATH')); ?></code></p>
        </div>
        <div class="card">
            <div><?php echo !empty($uploads['writable']) ? '<span class="ok">uploads/ listo</span>' : '<span class="warn">uploads/ no escribible</span>'; ?></div>
            <p>Permisos <?php echo crm_h((string) ($uploads['perm'] ?: 'n/d')); ?> · WebDAV <?php echo !empty($uploads['webdav_excluded']) ? 'excluido' : 'revisar'; ?></p>
        </div>
    </div>
    <div class="card" style="margin-top:1rem">
        <strong>API base</strong>
        <ul>
            <li><a href="api/health.php"><code>api/health.php</code></a> — PHP, extensiones, conector SQLite, uploads</li>
            <li><a href="api/inventory.php"><code>api/inventory.php</code></a> — catálogo / stock (<code>?code=SKU</code> o <code>?q=</code>)</li>
            <li><a href="api/fichas.php"><code>api/fichas.php</code></a> — fichas COMEX vinculadas a SKU</li>
            <li><a href="api/operaciones.php"><code>api/operaciones.php</code></a> — importaciones / exportaciones</li>
            <li><code>POST api/sync.php</code> — sincronizar fichas desde <code>prod.db</code></li>
            <li><a href="api/adjuntos.php?tipo=imagen&amp;sku=DEMO"><code>api/adjuntos.php</code></a> — imagen GD / PDF en <code>uploads/</code></li>
        </ul>
    </div>
</main>
</body>
</html>
