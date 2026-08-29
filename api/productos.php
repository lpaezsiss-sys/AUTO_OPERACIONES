<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    \Crm\Auth::requireUser();
    if (\Crm\Http::method() !== 'GET') {
        \Crm\Http::fail('Los productos de inventario son de solo lectura en el CRM', 405);
    }
    $id = \Crm\Http::idParam();
    if ($id > 0) {
        return array('producto' => \Crm\Productos::find($id));
    }
    return \Crm\Productos::index();
});
