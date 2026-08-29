<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    $user = \Crm\Auth::requireUser();
    $method = \Crm\Http::method();
    $id = \Crm\Http::idParam();
    if ($method === 'GET' && $id > 0) {
        return \Crm\Empresas::show($id);
    }
    if ($method === 'GET') {
        return \Crm\Empresas::index();
    }
    if ($method === 'POST') {
        return \Crm\Empresas::store(\Crm\Http::body(), $user);
    }
    if ($method === 'PUT' || $method === 'PATCH') {
        return \Crm\Empresas::update($id, \Crm\Http::body(), $user);
    }
    if ($method === 'DELETE') {
        return \Crm\Empresas::destroy($id);
    }
    \Crm\Http::fail('Método no permitido', 405);
    return array();
});
