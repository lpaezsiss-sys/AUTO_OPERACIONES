<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::jsonHeaders();
try {
    \Crm\Auth::requireUser();
    if (\Crm\Http::method() !== 'POST') {
        \Crm\Http::fail('Método no permitido', 405);
    }
    $file = (isset($_FILES['archivo']) && is_array($_FILES['archivo'])) ? $_FILES['archivo'] : array();
    $rel = \Crm\ItemImagen::guardarUpload($file);
    \Crm\Http::ok(array('imagen_url' => $rel));
} catch (\Crm\ApiException $e) {
    \Crm\Http::json(array('ok' => false, 'error' => $e->getMessage()) + $e->extra, $e->status);
}
