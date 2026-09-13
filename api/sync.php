<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function (): array {
    if (\Crm\Http::method() !== 'POST') {
        \Crm\Http::fail('Use POST para sincronizar el catálogo desde prod.db', 405);
    }
    $body = \Crm\Http::body();
    $sku = trim((string) ($body['sku'] ?? $_GET['sku'] ?? ''));
    $result = \Crm\Comex\Fichas::sincronizarDesdeInventario($sku !== '' ? $sku : null);
    $result['connector'] = \Crm\Inventory\SqliteConnector::status(crm_debug());
    return $result;
});
