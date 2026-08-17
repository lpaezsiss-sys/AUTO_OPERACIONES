#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Genera un PDF de cotización en local (no despliega a producción).
 *
 *   php scripts/probar_pdf_local.php
 *   php scripts/probar_pdf_local.php 17
 *   php scripts/probar_pdf_local.php 17 /tmp/cotizacion.pdf
 *   php scripts/probar_pdf_local.php --demo /tmp/cotizacion.pdf
 */

$root = dirname(__DIR__);
require $root . '/includes/bootstrap.php';

$pdo = crm_pdo();
$id = 0;
$demo = false;
$out = $root . '/downloads/cotizacion_local.pdf';
$args = array_values(array_slice($argv, 1));
foreach ($args as $arg) {
    if ($arg === '--demo') {
        $demo = true;
        continue;
    }
    if (preg_match('/\.pdf$/i', (string) $arg)) {
        $out = (string) $arg;
        continue;
    }
    if (ctype_digit((string) $arg)) {
        $id = (int) $arg;
    }
}
if ($demo) {
    $login = \Crm\Auth::login('ivan.p@example.net', 'Lpaezsis.2026');
    $prodId = (int) $pdo->query('SELECT id FROM productos WHERE activo = 1 ORDER BY id ASC LIMIT 1')->fetchColumn();
    $pack = \Crm\Cotizaciones::store(array(
        'empresa_id' => 2,
        'contacto_id' => 2,
        'estado' => 'enviada',
        'moneda' => 'CLP',
        'validez_oferta' => '30 días',
        'condiciones_pago' => '50% anticipo, 50% contra entrega',
        'plazo_entrega' => '15 días hábiles',
        'lugar_entrega' => 'Planta cliente, San Bernardo',
        'notas' => 'Oferta sujeta a stock de inventario. Instalación no incluye obra civil.',
        'items' => array(
            array(
                'tipo_item' => 'producto',
                'producto_id' => $prodId,
                'cantidad' => 2,
            ),
            array(
                'tipo_item' => 'servicio',
                'descripcion' => 'Instalación y puesta en marcha en planta',
                'cantidad' => 1,
                'precio_unitario' => 450000,
            ),
        ),
    ), $login);
    $id = (int) $pack['cotizacion']['id'];
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
