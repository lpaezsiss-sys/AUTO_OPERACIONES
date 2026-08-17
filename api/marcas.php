<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$method = \Crm\Http::method();
$id = \Crm\Http::idParam();
$file = (isset($_FILES['archivo']) && is_array($_FILES['archivo'])) ? $_FILES['archivo'] : null;

if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
    \Crm\Http::jsonHeaders();
    try {
        \Crm\Auth::requireUser();
        $data = \Crm\Http::body();
        if (is_array($_POST) && count($_POST) > 0) {
            foreach ($_POST as $k => $v) {
                if ($k === '_method') {
                    continue;
                }
                if (!isset($data[$k]) || $data[$k] === '') {
                    $data[$k] = $v;
                }
            }
        }
        if ($id <= 0 && isset($data['id'])) {
            $id = crm_int($data['id'], 0);
        }
        if ($id > 0) {
            $result = \Crm\Marcas::update($id, $data, $file);
        } else {
            $result = \Crm\Marcas::store($data, $file);
        }
        \Crm\Http::ok($result);
    } catch (\Crm\ApiException $e) {
        \Crm\Http::json(array('ok' => false, 'error' => $e->getMessage()) + $e->extra, $e->status);
    }
    exit;
}

\Crm\Http::handle(static function () use ($method, $id) {
    \Crm\Auth::requireUser();
    if ($method === 'GET' && $id > 0) {
        return \Crm\Marcas::show($id);
    }
    if ($method === 'GET') {
        return \Crm\Marcas::index();
    }
    if ($method === 'DELETE') {
        return \Crm\Marcas::destroy($id);
    }
    \Crm\Http::fail('Método no permitido', 405);
    return array();
});
