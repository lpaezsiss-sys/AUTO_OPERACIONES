<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    $user = \Crm\Auth::requireUser();
    $method = \Crm\Http::method();
    $id = \Crm\Http::idParam();
    $body = \Crm\Http::body();
    if ($id <= 0 && isset($body['id'])) {
        $id = crm_int($body['id'], 0);
    }

    if ($method === 'GET' && $id > 0) {
        $row = \Crm\ListasPrecios::obtener($id);
        if ($row === null) {
            \Crm\Http::fail('Lista de precios no encontrada', 404);
        }
        return array('lista' => $row);
    }
    if ($method === 'GET') {
        return \Crm\ListasPrecios::index();
    }
    if ($method === 'POST') {
        if ((string) $user['rol'] !== 'admin') {
            \Crm\Http::fail('Requiere rol administrador', 403);
        }
        return \Crm\ListasPrecios::guardar($body, 0);
    }
    if ($method === 'PUT' || $method === 'PATCH') {
        if ((string) $user['rol'] !== 'admin') {
            \Crm\Http::fail('Requiere rol administrador', 403);
        }
        if ($id <= 0) {
            \Crm\Http::fail('Debe indicar la lista');
        }
        return \Crm\ListasPrecios::guardar($body, $id);
    }
    if ($method === 'DELETE') {
        if ((string) $user['rol'] !== 'admin') {
            \Crm\Http::fail('Requiere rol administrador', 403);
        }
        if ($id <= 0) {
            \Crm\Http::fail('Debe indicar la lista');
        }
        return \Crm\ListasPrecios::eliminar($id);
    }
    \Crm\Http::fail('Método no permitido', 405);
    return array();
});
