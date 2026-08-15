<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    \Crm\Auth::requireUser();
    if (\Crm\Http::method() !== 'GET') {
        \Crm\Http::fail('Método no permitido', 405);
    }
    return \Crm\Dashboard::stats();
});
