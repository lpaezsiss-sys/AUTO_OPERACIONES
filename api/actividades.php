<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    $user = \Crm\Auth::requireUser();
    $method = \Crm\Http::method();
    $id = \Crm\Http::idParam();
    if ($method === 'GET' && $id > 0) {
        return \Crm\Actividades::one($id);
    }
    if ($method === 'GET') {
        return \Crm\Actividades::index();
    }
    if ($method === 'POST') {
        return \Crm\Actividades::store(\Crm\Http::body(), $user);
    }
    if ($method === 'PUT' || $method === 'PATCH') {
        return \Crm\Actividades::update($id, \Crm\Http::body(), $user);
    }
    \Crm\Http::fail('Método no permitido', 405);
    return array();
});
