<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    \Crm\Auth::requireUser();
    $method = \Crm\Http::method();
    if ($method === 'GET') {
        return \Crm\EstadisticasAPedido::obtener(array(
            'periodo' => isset($_GET['periodo']) ? $_GET['periodo'] : '',
            'desde' => isset($_GET['desde']) ? $_GET['desde'] : '',
            'hasta' => isset($_GET['hasta']) ? $_GET['hasta'] : '',
            'marca' => isset($_GET['marca']) ? $_GET['marca'] : '',
            'marca_id' => isset($_GET['marca_id']) ? $_GET['marca_id'] : 0,
        ));
    }
    if ($method === 'POST') {
        $body = \Crm\Http::body();
        $action = isset($body['action']) ? (string) $body['action'] : 'convertir_inventario';
        if ($action !== 'convertir_inventario') {
            \Crm\Http::fail('Acción no válida');
        }
        return \Crm\EstadisticasAPedido::convertirInventario($body);
    }
    \Crm\Http::fail('Método no permitido', 405);
    return array();
});
