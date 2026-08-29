<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    $user = \Crm\Auth::requireUser();
    $method = \Crm\Http::method();
    $id = \Crm\Http::idParam();
    if ($method === 'GET' && $id > 0) {
        return \Crm\Oportunidades::show($id);
    }
    if ($method === 'GET') {
        return \Crm\Oportunidades::index();
    }
    if ($method === 'POST') {
        return \Crm\Oportunidades::store(\Crm\Http::body(), $user);
    }
    if ($method === 'PUT' || $method === 'PATCH') {
        return \Crm\Oportunidades::update($id, \Crm\Http::body(), $user);
    }
    \Crm\Http::fail('Método no permitido', 405);
    return array();
});
