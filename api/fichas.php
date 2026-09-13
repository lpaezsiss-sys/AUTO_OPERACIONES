<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function (): array {
    $method = \Crm\Http::method();
    $sku = trim((string) ($_GET['sku'] ?? $_GET['code'] ?? ''));

    if ($method === 'POST') {
        $body = \Crm\Http::body();
        if ($sku === '') {
            $sku = trim((string) ($body['sku'] ?? ''));
        }
        return [
            'ficha' => \Crm\Comex\Fichas::guardar($body + ['sku' => $sku]),
        ];
    }

    if ($sku !== '') {
        $ficha = \Crm\Comex\Fichas::porSku($sku);
        if ($ficha === null) {
            \Crm\Http::fail('Ficha no encontrada', 404);
        }
        return ['ficha' => $ficha];
    }

    return ['fichas' => \Crm\Comex\Fichas::listar()];
});
