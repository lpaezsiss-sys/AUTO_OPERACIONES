#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Respaldo completo local: dump SQL + ZIP descargable.
 *
 *   php scripts/crear_respaldo.php
 */

$root = dirname(__DIR__);
require $root . '/includes/bootstrap.php';

echo '=== CRM respaldo completo ===' . PHP_EOL;
echo 'PHP ' . PHP_VERSION . ' | driver=' . crm_pdo_driver() . PHP_EOL;
echo PHP_EOL;

try {
    $r = \Crm\Respaldo::generar();
} catch (\Crm\ApiException $e) {
    fwrite(STDERR, 'FAIL  ' . $e->getMessage() . PHP_EOL);
    exit(1);
} catch (Exception $e) {
    fwrite(STDERR, 'FAIL  ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo 'SQL   sql/respaldo_completo_local.sql (' . number_format((int) $r['sql_bytes']) . ' bytes)' . PHP_EOL;
echo 'ZIP   ' . $r['ruta'] . PHP_EOL;
echo 'SIZE  ' . number_format((float) $r['mb'], 2, '.', '') . ' MB (' . number_format((int) $r['bytes']) . ' bytes)' . PHP_EOL;
echo 'FILES ' . (int) $r['archivos'] . PHP_EOL;
echo 'SQL IN ZIP: ' . (!empty($r['incluye_sql']) ? 'OK sql/respaldo_completo_local.sql' : 'FAIL') . PHP_EOL;
echo 'RAÍZ PLANA: ' . (!empty($r['raiz_plana']) ? 'OK index.php y .htaccess en la raíz del ZIP' : 'FAIL encapsulado') . PHP_EOL;
echo PHP_EOL;
echo 'URL de descarga local:' . PHP_EOL;
echo '  ' . $r['url'] . PHP_EOL;

$alt = array('http://localhost:8000', 'http://127.0.0.1:8000', 'http://127.0.0.1:8080');
$base = rtrim(\Crm\Respaldo::urlBase(), '/');
foreach ($alt as $b) {
    if ($b === $base) {
        continue;
    }
    echo '  ' . $b . '/downloads/' . $r['archivo'] . PHP_EOL;
}

$gh = 'https://github.com/lpaezsiss-sys/AUTO_OPERACIONES';
$tagOut = array();
exec('git -C ' . escapeshellarg($root) . ' describe --tags --abbrev=0 2>/dev/null', $tagOut);
$tag = isset($tagOut[0]) ? trim((string) $tagOut[0]) : '';
echo PHP_EOL;
echo 'GitHub:' . PHP_EOL;
echo '  Repo   ' . $gh . PHP_EOL;
if ($tag !== '') {
    echo '  Tag    ' . $tag . PHP_EOL;
    echo '  ZIP encapsulado (GitHub archive, carpeta AUTO_OPERACIONES-' . $tag . '/):' . PHP_EOL;
    echo '    ' . $gh . '/archive/refs/tags/' . rawurlencode($tag) . '.zip' . PHP_EOL;
    echo '  ZIP plano (Release, index.php en la raíz):' . PHP_EOL;
    echo '    ' . $gh . '/releases/tag/' . rawurlencode($tag) . PHP_EOL;
    echo '    ' . $gh . '/releases/download/' . rawurlencode($tag) . '/crm_lpaezsis_' . rawurlencode($tag) . '.zip' . PHP_EOL;
}
echo PHP_EOL;
echo 'RESULTADO: OK' . PHP_EOL;
exit(0);
