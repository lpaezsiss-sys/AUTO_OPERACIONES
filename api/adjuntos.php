<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function (): array {
    $tipo = strtolower((string) ($_GET['tipo'] ?? \Crm\Http::body()['tipo'] ?? 'imagen'));
    if ($tipo === 'pdf') {
        $id = (int) ($_GET['id'] ?? \Crm\Http::body()['id'] ?? 0);
        $op = \Crm\Comex\Operaciones::porId($id);
        if ($op === null) {
            \Crm\Http::fail('Operación no encontrada para PDF', 404);
        }
        $path = \Crm\Storage\DocumentoPdf::operacion($op);
        $abs = \Crm\Storage\Uploads::path(preg_replace('#^uploads/#', '', $path) ?? $path);
        return [
            'path' => $path,
            'bytes' => is_file($abs) ? filesize($abs) : 0,
            'writable' => is_file($abs) && is_writable(dirname($abs)),
            'perm' => is_file($abs) ? substr(sprintf('%o', (int) fileperms($abs)), -4) : '',
        ];
    }

    $sku = trim((string) ($_GET['sku'] ?? \Crm\Http::body()['sku'] ?? ''));
    $nombre = trim((string) ($_GET['nombre'] ?? \Crm\Http::body()['nombre'] ?? $sku));
    if ($sku === '') {
        \Crm\Http::fail('sku requerido para generar la imagen', 400);
    }
    $path = \Crm\Storage\ItemImagen::generarFicha($sku, $nombre);
    if ($sku !== '') {
        try {
            \Crm\Comex\Fichas::guardar(['sku' => $sku, 'nombre' => $nombre, 'imagen_path' => $path]);
        } catch (\Throwable) {
            // La imagen ya está en uploads/; la ficha puede crearse después.
        }
    }
    $abs = \Crm\Storage\Uploads::path(preg_replace('#^uploads/#', '', $path) ?? $path);
    return [
        'path' => $path,
        'bytes' => is_file($abs) ? filesize($abs) : 0,
        'writable' => is_writable(dirname($abs)),
        'dir_perm' => substr(sprintf('%o', (int) fileperms(dirname($abs))), -4),
    ];
});
