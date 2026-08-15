<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$action = isset($_GET['action']) ? trim((string) $_GET['action']) : '';
$method = \Crm\Http::method();

if ($method === 'POST' && $action === 'logo') {
    \Crm\Http::jsonHeaders();
    try {
        \Crm\Auth::requireAdmin();
        if (!isset($_FILES['logo']) || !is_array($_FILES['logo'])) {
            \Crm\Http::fail('Debe adjuntar el archivo logo.');
        }
        $file = $_FILES['logo'];
        $err = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($err !== UPLOAD_ERR_OK) {
            \Crm\Http::fail('No se pudo cargar el logo.');
        }
        $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            \Crm\Http::fail('Archivo de logo inválido.');
        }
        if ($size <= 0 || $size > 2097152) {
            \Crm\Http::fail('El logo no debe superar 2 MB.');
        }
        $info = @getimagesize($tmp);
        if (!is_array($info) || empty($info['mime'])) {
            \Crm\Http::fail('El archivo no es una imagen válida.');
        }
        $extMap = array(
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        );
        $mime = (string) $info['mime'];
        if (!isset($extMap[$mime])) {
            \Crm\Http::fail('Use PNG, JPG, GIF o WebP.');
        }
        $dir = dirname(__DIR__) . '/uploads';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            \Crm\Http::fail('No se pudo crear el directorio de uploads.', 500);
        }
        $rel = 'uploads/logo.' . $extMap[$mime];
        $dest = dirname(__DIR__) . '/' . $rel;
        if (!move_uploaded_file($tmp, $dest)) {
            \Crm\Http::fail('No se pudo guardar el logo.', 500);
        }
        \Crm\Http::ok(\Crm\ConfiguracionEmpresa::actualizarLogo($rel));
    } catch (\Crm\ApiException $e) {
        \Crm\Http::json(array('ok' => false, 'error' => $e->getMessage()) + $e->extra, $e->status);
    }
    exit;
}

\Crm\Http::handle(static function () use ($method) {
    $user = \Crm\Auth::requireUser();
    if ($method === 'GET') {
        return array('configuracion' => \Crm\ConfiguracionEmpresa::obtener());
    }
    if ($method === 'PUT' || $method === 'PATCH' || $method === 'POST') {
        if ((string) $user['rol'] !== 'admin') {
            \Crm\Http::fail('Requiere rol administrador', 403);
        }
        return \Crm\ConfiguracionEmpresa::guardar(\Crm\Http::body());
    }
    \Crm\Http::fail('Método no permitido', 405);
    return array();
});
