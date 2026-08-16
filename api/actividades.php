<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    $user = \Crm\Auth::requireUser();
    $method = \Crm\Http::method();
    $id = \Crm\Http::idParam();
    $body = \Crm\Http::body();
    $action = isset($_GET['action']) ? (string) $_GET['action'] : '';
    if ($action === '' && isset($body['action'])) {
        $action = (string) $body['action'];
    }
    $action = crm_lower(trim($action));

    if ($method === 'GET' && $id > 0) {
        return \Crm\Actividades::one($id);
    }
    if ($method === 'GET') {
        return \Crm\Actividades::index($_GET);
    }
    if ($method === 'POST' && ($action === 'completar' || $action === 'realizar')) {
        $cid = $id > 0 ? $id : \Crm\Http::idParam();
        if ($cid <= 0 && isset($body['id'])) {
            $cid = crm_int($body['id'], 0);
        }
        return \Crm\Actividades::completar($cid, $body, $user);
    }
    if ($method === 'POST' && ($action === 'crear' || $action === '' || $action === 'store')) {
        return \Crm\Actividades::crear($body, $user);
    }
    if ($method === 'PUT' || $method === 'PATCH') {
        if ($action === 'completar' || $action === 'realizar') {
            return \Crm\Actividades::completar($id, $body, $user);
        }
        return \Crm\Actividades::update($id, $body, $user);
    }
    \Crm\Http::fail('Método no permitido', 405);
    return array();
});
