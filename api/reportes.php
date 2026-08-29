<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    \Crm\Auth::requireUser();
    if (\Crm\Http::method() !== 'GET') {
        \Crm\Http::fail('Método no permitido', 405);
    }
    $tipo = isset($_GET['tipo']) ? (string) $_GET['tipo'] : '';
    if ($tipo === '') {
        \Crm\Http::fail('Debe indicar tipo de reporte');
    }
    return \Crm\Reportes::obtener($tipo, array(
        'desde' => isset($_GET['desde']) ? $_GET['desde'] : '',
        'hasta' => isset($_GET['hasta']) ? $_GET['hasta'] : '',
        'vendedor_id' => isset($_GET['vendedor_id']) ? $_GET['vendedor_id'] : 0,
    ));
});
