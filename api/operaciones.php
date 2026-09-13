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
    if ($action === '' && isset($body['action'])) {
        $action = strtolower((string) $body['action']);
    }

    if ($method === 'POST' && $action === 'confirmar') {
        return ['operacion' => \Crm\Comex\Operaciones::confirmar($id)];
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
