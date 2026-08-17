#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Genera un PDF de cotización en local (no despliega a producción).
 *
 *   php scripts/probar_pdf_local.php
 *   php scripts/probar_pdf_local.php 17
 *   php scripts/probar_pdf_local.php 17 /tmp/cotizacion.pdf
 */

$root = dirname(__DIR__);
require $root . '/includes/bootstrap.php';

$pdo = crm_pdo();
$id = 0;
$out = $root . '/downloads/cotizacion_local.pdf';
if (isset($argv[1]) && preg_match('/\.pdf$/i', (string) $argv[1])) {
    $out = (string) $argv[1];
} elseif (isset($argv[1])) {
    $id = (int) $argv[1];
}
if (isset($argv[2])) {
    $out = (string) $argv[2];
}
if ($id <= 0) {
    $id = (int) $pdo->query('SELECT id FROM crm_cotizaciones ORDER BY id DESC LIMIT 1')->fetchColumn();
}
if ($id <= 0) {
    fwrite(STDERR, "FAIL  no hay cotizaciones en la BD local\n");
    exit(1);
}

$pack = \Crm\Cotizaciones::show($id);
$cot = $pack['cotizacion'];
$bin = \Crm\CotizacionPdf::generar($cot);
if ($out !== '' && $out[0] !== '/') {
    $out = $root . '/' . ltrim($out, '/');
}
$dir = dirname($out);
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
file_put_contents($out, $bin);
echo 'ID    ' . $id . PHP_EOL;
echo 'FOLIO ' . (isset($cot['folio']) ? $cot['folio'] : '') . PHP_EOL;
echo 'PDF   ' . $out . PHP_EOL;
echo 'SIZE  ' . strlen($bin) . PHP_EOL;
echo 'HEAD  ' . substr($bin, 0, 8) . PHP_EOL;
echo 'OK' . PHP_EOL;
exit(0);
