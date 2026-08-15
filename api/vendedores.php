<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    $user = \Crm\Auth::requireUser();
    $method = \Crm\Http::method();
    $id = \Crm\Http::idParam();
    if ($method === 'GET' && $id > 0) {
        $row = \Crm\Vendedores::obtener(crm_pdo(), $id);
        if (!$row) {
            \Crm\Http::fail('Vendedor no encontrado', 404);
        }
        return array('vendedor' => $row);
    }
    if ($method === 'GET') {
        return \Crm\Vendedores::index();
    }
    if ($method === 'POST') {
        if ((string) $user['rol'] !== 'admin') {
            \Crm\Http::fail('Requiere rol administrador', 403);
        }
        return \Crm\Vendedores::guardar(\Crm\Http::body(), 0);
    }
    if ($method === 'PUT' || $method === 'PATCH') {
        if ((string) $user['rol'] !== 'admin') {
            \Crm\Http::fail('Requiere rol administrador', 403);
        }
        if ($id <= 0) {
            \Crm\Http::fail('Debe indicar el vendedor');
        }
        return \Crm\Vendedores::guardar(\Crm\Http::body(), $id);
    }
    if ($method === 'DELETE') {
        if ((string) $user['rol'] !== 'admin') {
            \Crm\Http::fail('Requiere rol administrador', 403);
        }
        if ($id <= 0) {
            \Crm\Http::fail('Debe indicar el vendedor');
        }
        return \Crm\Vendedores::eliminar($id);
    }
    \Crm\Http::fail('Método no permitido', 405);
    return array();
});
