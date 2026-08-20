<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    \Crm\Auth::requireAdmin();
    $method = \Crm\Http::method();
    if ($method !== 'PUT' && $method !== 'PATCH' && $method !== 'POST') {
        \Crm\Http::fail('Método no permitido', 405);
    }
    $body = \Crm\Http::body();
    $id = \Crm\Http::idParam();
    return \Crm\Cotizaciones::actualizarFolio($id, $body);
});
