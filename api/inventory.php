<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function (): array {
    $q = trim((string) ($_GET['q'] ?? $_GET['sku'] ?? ''));
    $sku = trim((string) ($_GET['code'] ?? ''));

    if ($sku !== '') {
        $stock = \Crm\Inventory\InventarioStock::stockPorCodigo($sku);
        if ($stock === null && !\Crm\Inventory\InventarioStock::disponible()) {
            \Crm\Http::fail('Inventario SQLite no disponible', 503);
        }
        return [
            'code' => $sku,
            'stock' => $stock,
            'found' => $stock !== null,
        ];
    }

    if ($q === '') {
        return [
            'inventory' => \Crm\Inventory\InventarioStock::status(crm_debug()),
            'items' => [],
        ];
    }

    return [
        'q' => $q,
        'items' => \Crm\Inventory\InventarioStock::buscar($q),
    ];
});
