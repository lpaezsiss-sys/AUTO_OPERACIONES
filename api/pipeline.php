<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

\Crm\Http::handle(static function (): array {
    $method = \Crm\Http::method();
    $body = \Crm\Http::body();
    $action = strtolower((string) ($_GET['action'] ?? $body['action'] ?? ''));
    $operacionId = (int) ($_GET['operacion_id'] ?? $body['operacion_id'] ?? 0);
    $etapaId = (int) ($_GET['etapa_id'] ?? $body['etapa_id'] ?? $body['id'] ?? 0);

    if ($method === 'POST' && ($action === 'crear' || $action === 'create')) {
        return \Crm\Comex\Pipeline::crearOperacion($body);
    }
    if ($method === 'POST' && ($action === 'actualizar' || $action === 'update')) {
        if ($etapaId <= 0) {
            \Crm\Http::fail('etapa_id requerido', 400);
        }
        return \Crm\Comex\Pipeline::actualizarEtapa($etapaId, $body);
    }
    if ($method === 'POST' && $action === 'avanzar') {
        if ($operacionId <= 0) {
            \Crm\Http::fail('operacion_id requerido', 400);
        }
        return \Crm\Comex\Pipeline::avanzar(
            $operacionId,
            isset($body['comentario']) ? (string) $body['comentario'] : null,
            (string) ($body['autor'] ?? 'COMEX')
        );
    }

    if ($operacionId > 0) {
        return \Crm\Comex\Pipeline::paraOperacion($operacionId);
    }

    return \Crm\Comex\Pipeline::tablero();
});
