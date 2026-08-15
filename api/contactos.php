<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    \Crm\Auth::requireUser();
    $method = \Crm\Http::method();
    $id = \Crm\Http::idParam();
    if ($method === 'GET' && $id > 0) {
        return \Crm\Contactos::one($id);
    }
    if ($method === 'GET') {
        return \Crm\Contactos::index();
    }
    if ($method === 'POST') {
        return \Crm\Contactos::store(\Crm\Http::body());
    }
    if ($method === 'PUT' || $method === 'PATCH') {
        return \Crm\Contactos::update($id, \Crm\Http::body());
    }
    if ($method === 'DELETE') {
        return \Crm\Contactos::destroy($id);
    }
    \Crm\Http::fail('Método no permitido', 405);
    return array();
});
