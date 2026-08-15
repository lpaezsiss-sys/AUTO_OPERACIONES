<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$method = \Crm\Http::method();

if ($method === 'POST') {
    \Crm\Http::jsonHeaders();
    try {
        \Crm\Auth::requireAdmin();
        $data = \Crm\Http::body();
        if (is_array($_POST) && count($_POST) > 0) {
            foreach ($_POST as $k => $v) {
                if (!isset($data[$k]) || $data[$k] === '') {
                    $data[$k] = $v;
                }
            }
        }

        $result = null;
        $hasFields = trim((string) (isset($data['rut']) ? $data['rut'] : '')) !== ''
            || trim((string) (isset($data['razon_social']) ? $data['razon_social'] : '')) !== ''
            || trim((string) (isset($data['direccion']) ? $data['direccion'] : '')) !== '';
        if ($hasFields) {
            $result = \Crm\ConfiguracionEmpresa::guardar($data);
        }

        if (isset($_FILES['logo']) && is_array($_FILES['logo']) && (int) $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $rel = \Crm\ConfiguracionEmpresa::guardarArchivoLogo($_FILES['logo'], true);
            $result = \Crm\ConfiguracionEmpresa::actualizarLogo($rel);
        }

        if ($result === null) {
            \Crm\Http::fail('Sin datos para actualizar.');
        }
        \Crm\Http::ok($result);
    } catch (\Crm\ApiException $e) {
        \Crm\Http::json(array('ok' => false, 'error' => $e->getMessage()) + $e->extra, $e->status);
    }
    exit;
}

\Crm\Http::handle(static function () use ($method) {
    \Crm\Auth::requireUser();
    if ($method === 'GET') {
        return array('configuracion' => \Crm\ConfiguracionEmpresa::obtener());
    }
    \Crm\Http::fail('Método no permitido', 405);
    return array();
});
