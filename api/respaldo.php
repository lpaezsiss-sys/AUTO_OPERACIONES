<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$action = isset($_GET['action']) ? crm_lower(trim((string) $_GET['action'])) : '';
if ($action === '' && \Crm\Http::method() === 'POST') {
    $body = \Crm\Http::body();
    if (isset($body['action'])) {
        $action = crm_lower(trim((string) $body['action']));
    }
    if ($action === '') {
        $action = 'generar';
    }
}

if ($action === 'descargar') {
    \Crm\Auth::requireAdmin();
    $nombre = isset($_GET['archivo']) ? (string) $_GET['archivo'] : '';
    $full = \Crm\Respaldo::rutaSegura($nombre);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($full) . '"');
    header('Content-Length: ' . (string) filesize($full));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    readfile($full);
    exit;
}

\Crm\Http::handle(static function () use ($action) {
    \Crm\Auth::requireAdmin();
    if ($action === 'generar') {
        return \Crm\Respaldo::generar();
    }
    return \Crm\Respaldo::ultimo();
});
