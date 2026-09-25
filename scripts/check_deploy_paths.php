#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Verifica la estructura de despliegue BlueHosting (raíz = document root).
 *
 *   php scripts/check_deploy_paths.php
 */

$root = dirname(__DIR__);
$pass = 0;
$fail = 0;

function deploy_check(bool $ok, string $name, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo 'PASS  ' . $name;
    } else {
        $fail++;
        echo 'FAIL  ' . $name;
    }
    if ($detail !== '') {
        echo ' — ' . $detail;
    }
    echo PHP_EOL;
}

echo '=== COMEX LPAEZSIS — rutas de despliegue ===' . PHP_EOL;
echo 'Raíz: ' . $root . PHP_EOL . PHP_EOL;

$archivosRaiz = [
    'index.php' => 'Portada COMEX',
    '.htaccess' => 'Apache: 403, HTTPS, front controller',
    '.env.example' => 'Plantilla de entorno',
    '.env.production' => 'Plantilla producción (INV_SQLITE_PATH)',
    '.webdavignore' => 'Exclusiones WebDAV 2078',
    'landed.php' => 'Módulo landed cost',
    'operaciones.php' => 'Pipeline Kanban / lista',
    'operacion.php' => 'Detalle de etapas y bitácora',
    'crm.php' => 'CRM perfiles de usuario admin/comex',
    'login.php' => 'Inicio de sesión obligatorio',
    'src/Auth.php' => 'Auth isLoggedIn/requireLogin (user_id)',
    'src/Schema.php' => 'Migración tabla usuarios',
    'api/usuarios.php' => 'API login y listado de perfiles',
    'assets/js/login.js' => 'Login panel con toast y api/usuarios.php',
    'assets/js/crm-perfiles.js' => 'Login CRM con toast',
    'assets/js/items.js' => 'Ítems de operación y SKU de evaluación',
    'assets/js/operacion-crud.js' => 'Editar y eliminar operaciones',
    'manual.php' => 'Manual de operación',
    'fichas.php' => 'Fichas de producto (SKU inventario)',
    'api/sync.php' => 'Sincronizar catálogo desde prod.db',
    'api/fichas.php' => 'API listado de fichas',
    'includes/layout_header.php' => 'Encabezado del panel',
    'includes/layout_footer.php' => 'Pie del panel',
    'assets/js/financials.js' => 'Hoja landed cost en detalle',
    'assets/js/documentos.js' => 'Repositorio documental',
    'assets/js/dashboard-chart.js' => 'Gráfico volumen mensual',
    'api/documentos.php' => 'API documentos',
    'api/dashboard.php' => 'API dashboard KPIs',
    'assets/css/app.css' => 'Estilos panel CRM',
    'composer.json' => 'PSR-4 Crm\\ → src/Crm/',
];
foreach ($archivosRaiz as $rel => $label) {
    $path = $root . '/' . $rel;
    deploy_check(is_file($path), 'Raíz: ' . $rel, is_file($path) ? $label : 'ausente');
}

        $dirs = ['api', 'src/Crm', 'src/Crm/Inventory', 'src/Crm/Comex', 'src/Crm/Storage', 'includes', 'uploads', 'uploads/comex', 'uploads/comex/productos', 'uploads/comex/items', 'uploads/comex/pdf', 'uploads/comex/xlsx', 'uploads/comex/docs', 'assets/css', 'assets/js', 'config', 'sql', 'data', 'scripts', 'deploy', 'tests'];
foreach ($dirs as $dir) {
    deploy_check(is_dir($root . '/' . $dir), 'Carpeta: ' . $dir . '/', is_dir($root . '/' . $dir) ? 'OK' : 'ausente');
}

$denyDirs = ['config', 'src', 'includes', 'sql', 'tests', 'data', 'scripts', 'deploy'];
foreach ($denyDirs as $dir) {
    $ht = $root . '/' . $dir . '/.htaccess';
    $body = is_file($ht) ? (string) file_get_contents($ht) : '';
    deploy_check(
        str_contains($body, 'Require all denied') || str_contains($body, 'Deny from all'),
        $dir . '/.htaccess deniega HTTP',
        is_file($ht) ? '403' : 'falta .htaccess'
    );
}

$srcEntries = scandir($root . '/src') ?: [];
deploy_check(in_array('Crm', $srcEntries, true), 'src/Crm case-sensitive', 'namespace Crm\\');

$ht = is_file($root . '/.htaccess') ? (string) file_get_contents($root . '/.htaccess') : '';
deploy_check((bool) preg_match('/DirectoryIndex\s+index\.php/i', $ht), '.htaccess DirectoryIndex');
deploy_check((bool) preg_match('/Options\s+-Indexes/i', $ht), '.htaccess Options -Indexes');
deploy_check(str_contains($ht, 'Require all denied'), '.htaccess 403 .env');
deploy_check(str_contains($ht, 'RewriteCond %{HTTPS} !=on'), '.htaccess HTTPS');

$example = is_file($root . '/.env.example') ? (string) file_get_contents($root . '/.env.example') : '';
deploy_check(
    str_contains($example, 'INV_SQLITE_PATH=/home/sistem29/app/data/prod.db'),
    'INV_SQLITE_PATH producción',
    '/home/sistem29/app/data/prod.db'
);

$upMode = is_dir($root . '/uploads') ? ((int) fileperms($root . '/uploads') & 0777) : 0;
deploy_check(in_array($upMode, [0755, 0775], true), 'uploads/ permisos 755/775', decoct($upMode));
deploy_check(is_file($root . '/uploads/.webdav-exclude'), 'uploads/.webdav-exclude');
deploy_check(is_file($root . '/uploads/.htaccess'), 'uploads/.htaccess (sin PHP)');

echo PHP_EOL;
echo 'Document root cPanel: public_html/comex.lpaezsis.cl/' . PHP_EOL;
echo 'Resultado: ' . $pass . ' PASS / ' . $fail . ' FAIL' . PHP_EOL;
if ($fail > 0) {
    echo 'FAIL' . PHP_EOL;
    exit(1);
}
echo 'PASS' . PHP_EOL;
exit(0);
