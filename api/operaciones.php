<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function (): array {
    $method = \Crm\Http::method();
    $id = (int) ($_GET['id'] ?? 0);
    $action = strtolower((string) ($_GET['action'] ?? ''));
    $body = \Crm\Http::body();
    if ($id <= 0 && isset($body['id'])) {
        $id = (int) $body['id'];
    }
    if ($id <= 0 && isset($body['operacion_id'])) {
        $id = (int) $body['operacion_id'];
    }
    if ($action === '' && isset($body['action'])) {
        $action = strtolower((string) $body['action']);
    }

    if ($method === 'POST' && $action === 'confirmar') {
        return ['operacion' => \Crm\Comex\Operaciones::confirmar($id)];
    }

    if ($method === 'POST' && ($action === 'agregar_items' || $action === 'items')) {
        $rawItems = $body['items'] ?? null;
        if (!is_array($rawItems) && isset($body['sku'])) {
            $rawItems = [$body];
        }
        if (!is_array($rawItems)) {
            \Crm\Http::fail('Ítems inválidos', 400);
        }
        return ['operacion' => \Crm\Comex\Operaciones::agregarItems($id, $rawItems)];
    }

    if ($method === 'POST' && $action === 'vincular') {
        $itemId = (int) ($body['item_id'] ?? $body['id'] ?? 0);
        $skuOficial = trim((string) ($body['sku_oficial'] ?? $body['sku'] ?? ''));
        return ['operacion' => \Crm\Comex\Operaciones::vincularItem($itemId, $skuOficial)];
    }

    if ($method === 'POST') {
        return ['operacion' => \Crm\Comex\Operaciones::crear($body)];
    }

    if ($id > 0) {
        $op = \Crm\Comex\Operaciones::porId($id);
        if ($op === null) {
            \Crm\Http::fail('Operación no encontrada', 404);
        }
        return ['operacion' => $op];
    }

    return ['operaciones' => \Crm\Comex\Operaciones::listar()];
});
