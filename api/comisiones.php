<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    $user = \Crm\Auth::requireUser();
    $method = \Crm\Http::method();
    $id = \Crm\Http::idParam();
    if ($method === 'GET') {
        return \Crm\Comisiones::index(array(
            'estado' => isset($_GET['estado']) ? $_GET['estado'] : '',
            'vendedor_id' => isset($_GET['vendedor_id']) ? $_GET['vendedor_id'] : 0,
        ));
    }
    if ($method === 'PUT' || $method === 'PATCH') {
        if ((string) $user['rol'] !== 'admin') {
            \Crm\Http::fail('Requiere rol administrador', 403);
        }
        if ($id <= 0) {
            \Crm\Http::fail('Debe indicar la comisión');
        }
        $body = \Crm\Http::body();
        $estado = isset($body['estado']) ? (string) $body['estado'] : '';
        $fecha = isset($body['fecha_liquidacion']) ? $body['fecha_liquidacion'] : null;
        return \Crm\Comisiones::actualizarEstado($id, $estado, $fecha);
    }
    \Crm\Http::fail('Método no permitido', 405);
    return array();
});
