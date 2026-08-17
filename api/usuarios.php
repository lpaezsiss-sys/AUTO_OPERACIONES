<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    \Crm\Auth::requireAdmin();
    $method = \Crm\Http::method();
    $id = \Crm\Http::idParam();
    $body = \Crm\Http::body();
    if ($id <= 0 && isset($body['id'])) {
        $id = crm_int($body['id'], 0);
    }

    if ($method === 'GET' && $id > 0) {
        $row = \Crm\Usuarios::obtener($id);
        if ($row === null) {
            \Crm\Http::fail('Usuario no encontrado', 404);
        }
        return array('usuario' => $row);
    }
    if ($method === 'GET') {
        return \Crm\Usuarios::index();
    }
    if ($method === 'POST') {
        $accion = isset($body['accion']) ? (string) $body['accion'] : '';
        if ($accion === 'password') {
            if ($id <= 0) {
                \Crm\Http::fail('Debe indicar el usuario');
            }
            return \Crm\Usuarios::cambiarPassword($id, isset($body['password']) ? $body['password'] : '');
        }
        return \Crm\Usuarios::guardar($body, 0);
    }
    if ($method === 'PUT' || $method === 'PATCH') {
        if ($id <= 0) {
            \Crm\Http::fail('Debe indicar el usuario');
        }
        $accion = isset($body['accion']) ? (string) $body['accion'] : '';
        if ($accion === 'password') {
            return \Crm\Usuarios::cambiarPassword($id, isset($body['password']) ? $body['password'] : '');
        }
        return \Crm\Usuarios::guardar($body, $id);
    }
    \Crm\Http::fail('Método no permitido', 405);
    return array();
});
