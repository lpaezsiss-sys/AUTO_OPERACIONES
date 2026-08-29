#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * ZIP plano del código (sin .env ni .git). No requiere base de datos.
 *
 *   php scripts/empaquetar_zip.php
 *   php scripts/empaquetar_zip.php downloads/crm_lpaezsis_YYYYMMDD_HHMM.zip
 */

$root = dirname(__DIR__);
require $root . '/src/Respaldo.php';

$stamp = date('Ymd_Hi');
$out = isset($argv[1]) ? (string) $argv[1] : $root . '/downloads/crm_lpaezsis_' . $stamp . '.zip';
if ($out !== '' && $out[0] !== '/') {
    $out = $root . '/' . ltrim($out, '/');
}
$dir = dirname($out);
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    fwrite(STDERR, 'FAIL  no se pudo crear ' . $dir . PHP_EOL);
    exit(1);
}
if (is_file($out)) {
    unlink($out);
}

$archivos = array();
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $full = $file->getPathname();
    $rel = substr($full, strlen($root) + 1);
    $rel = str_replace('\\', '/', (string) $rel);
    if (\Crm\Respaldo::excluir($rel)) {
        continue;
    }
    $archivos[] = $rel;
}
sort($archivos);

$okZip = false;
if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        foreach ($archivos as $rel) {
            $full = $root . '/' . $rel;
            if (is_file($full)) {
                $zip->addFile($full, $rel);
            }
        }
        $zip->close();
        $okZip = is_file($out);
    }
}
if (!$okZip) {
    $bin = trim((string) shell_exec('command -v zip'));
    if ($bin === '') {
        fwrite(STDERR, 'FAIL  se requiere ZipArchive o el comando zip' . PHP_EOL);
        exit(1);
    }
    $listFile = sys_get_temp_dir() . '/crm-pack-' . uniqid('', true) . '.txt';
    file_put_contents($listFile, implode("\n", $archivos) . "\n");
    $cmd = 'cd ' . escapeshellarg($root) . ' && ' . escapeshellarg($bin)
        . ' -q ' . escapeshellarg($out) . ' -@ < ' . escapeshellarg($listFile);
    $pipeOut = array();
    $code = 0;
    exec($cmd . ' 2>&1', $pipeOut, $code);
    if (is_file($listFile)) {
        unlink($listFile);
    }
    if ($code !== 0 || !is_file($out)) {
        fwrite(STDERR, 'FAIL  zip CLI: ' . implode("\n", $pipeOut) . PHP_EOL);
        exit(1);
    }
}

$bytes = is_file($out) ? (int) filesize($out) : 0;
$okIndex = \Crm\Respaldo::zipContiene($out, 'index.php');
$okHt = \Crm\Respaldo::zipContiene($out, '.htaccess');
echo 'ZIP   ' . str_replace($root . '/', '', $out) . PHP_EOL;
echo 'SIZE  ' . number_format($bytes) . ' bytes' . PHP_EOL;
echo 'FILES ' . count($archivos) . PHP_EOL;
echo 'RAÍZ  ' . ($okIndex && $okHt ? 'OK index.php y .htaccess' : 'FAIL encapsulado') . PHP_EOL;
if ($bytes <= 0 || !$okIndex || !$okHt) {
    exit(1);
}
echo 'OK' . PHP_EOL;
exit(0);
