#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Verificación local del CRM (PHP 7.4 / SQLite o MySQL).
 *
 *   php scripts/test_local.php
 */

$root = dirname(__DIR__);
$pass = 0;
$fail = 0;

/**
 * @param bool $ok
 * @param string $name
 * @param string $detail
 * @return void
 */
function crm_local_report($ok, $name, $detail)
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

echo '=== CRM verificación local ===' . PHP_EOL;
echo 'PHP ' . PHP_VERSION . PHP_EOL;
if (PHP_VERSION_ID >= 80000) {
    echo 'NOTA: este intérprete es PHP 8+. En BlueHosting usa PHP 7.4; php -l aquí no detecta APIs de PHP 8.' . PHP_EOL;
}
echo PHP_EOL;

$phpBin = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';

/* a) php -l en config/, api/ y raíz */
$targets = array();
foreach (array($root . '/config', $root . '/api', $root) as $dir) {
    $list = glob($dir . '/*.php');
    if (!is_array($list)) {
        $list = array();
    }
    foreach ($list as $file) {
        $targets[$file] = true;
    }
}
ksort($targets);

$lintFail = 0;
$lintOk = 0;
foreach (array_keys($targets) as $file) {
    $cmd = escapeshellarg($phpBin) . ' -l ' . escapeshellarg($file) . ' 2>&1';
    $out = array();
    $code = 0;
    exec($cmd, $out, $code);
    $text = implode("\n", $out);
    $rel = str_replace($root . '/', '', $file);
    if ($code === 0 && strpos($text, 'No syntax errors') !== false) {
        $lintOk++;
    } else {
        $lintFail++;
        crm_local_report(false, 'php -l ' . $rel, $text);
    }
}
crm_local_report(
    $lintFail === 0,
    'Escaneo de sintaxis php -l (config/, api/, raíz)',
    $lintOk . ' archivo(s) OK' . ($lintFail > 0 ? ', ' . $lintFail . ' con error' : '')
);

/* b) lectura .env o .env.example */
$envFile = $root . '/.env';
$envExample = $root . '/.env.example';
$envUsed = '';
if (is_file($envFile) && is_readable($envFile)) {
    $envUsed = $envFile;
} elseif (is_file($envExample) && is_readable($envExample)) {
    $envUsed = $envExample;
}

if ($envUsed === '') {
    crm_local_report(false, 'Lectura de .env / .env.example', 'ningún archivo encontrado');
} else {
    $lines = file($envUsed, FILE_IGNORE_NEW_LINES);
    $okRead = is_array($lines) && count($lines) > 0;
    $keys = 0;
    if ($okRead) {
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                $keys++;
            }
        }
    }
    crm_local_report(
        $okRead && $keys > 0,
        'Lectura de ' . str_replace($root . '/', '', $envUsed),
        $keys . ' variable(s)'
    );
}

/* c) conexión BD */
require $root . '/includes/bootstrap.php';

$dbOk = false;
$driver = '';
$dbDetail = '';
try {
    $pdo = crm_pdo();
    $pdo->query('SELECT 1');
    $driver = crm_pdo_driver();
    $dbOk = true;
    $dbDetail = 'driver=' . $driver;
} catch (Exception $e) {
    $dbDetail = $e->getMessage();
}
crm_local_report($dbOk, 'Conexión PDO (SQLite o MySQL según .env)', $dbDetail);

/* d) SELECT solo lectura a productos */
$prodOk = false;
$prodDetail = '';
if ($dbOk) {
    try {
        $stmt = crm_pdo()->query(
            'SELECT id, codigo, nombre, stock, precio_unitario FROM productos LIMIT 5'
        );
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
        if (!is_array($rows)) {
            $rows = array();
        }
        $n = count($rows);
        $prodOk = $n > 0;
        if ($prodOk) {
            $first = $rows[0];
            $sku = isset($first['codigo']) ? (string) $first['codigo'] : '';
            $prodDetail = $n . ' fila(s) leídas, primer SKU=' . $sku;
        } else {
            $prodDetail = 'tabla vacía (ejecuta: php sql/install.php)';
        }
    } catch (Exception $e) {
        $prodDetail = $e->getMessage();
    }
} else {
    $prodDetail = 'omitido: sin conexión';
}
crm_local_report($prodOk, 'SELECT de solo lectura sobre productos', $prodDetail);

echo PHP_EOL;
echo 'Resultado: ' . $pass . ' PASS / ' . $fail . ' FAIL' . PHP_EOL;
if ($fail > 0) {
    echo 'FAIL' . PHP_EOL;
    exit(1);
}
echo 'PASS' . PHP_EOL;
exit(0);
