<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function (): array {
    $q = trim((string) ($_GET['q'] ?? $_GET['sku'] ?? ''));
    $sku = trim((string) ($_GET['code'] ?? ''));

    if ($sku !== '') {
        $prod = \Crm\Inventory\Catalogo::porCodigo($sku);
        if ($prod === null && !\Crm\Inventory\InventarioStock::disponible()) {
            \Crm\Http::fail('Inventario SQLite no disponible', 503);
        }
        $ficha = \Crm\Comex\Fichas::porSku($sku);
        return [
            'code' => $sku,
            'stock' => $prod['stock'] ?? null,
            'found' => $prod !== null,
            'product' => $prod,
            'ficha' => $ficha,
        ];
    }

    if ($q === '') {
        return [
            'inventory' => \Crm\Inventory\SqliteConnector::status(crm_debug()),
            'items' => [],
        ];
    }

    return [
        'q' => $q,
        'items' => \Crm\Inventory\InventarioStock::buscar($q),
    ];
});
