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
echo PHP_EOL;
echo 'RESULTADO: OK' . PHP_EOL;
exit(0);
