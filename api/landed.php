<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

\Crm\Http::handle(static function (): array {
    $method = \Crm\Http::method();
    $body = \Crm\Http::body();
    $action = strtolower((string) ($_GET['action'] ?? $body['action'] ?? ''));
    $operacionId = (int) ($_GET['operacion_id'] ?? $body['operacion_id'] ?? 0);
    $id = (int) ($_GET['id'] ?? $body['id'] ?? 0);

    if ($method === 'POST' && $action === 'calcular') {
        return ['calculo' => \Crm\Comex\LandedCostStore::calcularDesde($body)];
    }
    if ($method === 'POST' && $action === 'guardar') {
        return ['evaluacion' => \Crm\Comex\LandedCostStore::guardar($body)];
    }
    if ($method === 'POST' && $action === 'pdf') {
        if ($id <= 0 && $operacionId > 0) {
            $ver = strtoupper((string) ($body['version'] ?? 'ESTIMADA'));
            $row = \Crm\Comex\LandedCostStore::porVersion($operacionId, $ver);
            $id = is_array($row) ? (int) $row['id'] : 0;
        }
        return ['evaluacion' => \Crm\Comex\LandedCostStore::exportarPdf($id)];
    }

    if ($id > 0) {
        $row = \Crm\Comex\LandedCostStore::porId($id);
        if ($row === null) {
            \Crm\Http::fail('Evaluación no encontrada', 404);
        }
        return ['evaluacion' => $row];
    }
    if ($operacionId > 0) {
        return \Crm\Comex\LandedCostStore::paraOperacion($operacionId);
    }

    $ops = \Crm\Comex\Operaciones::listar();
    return ['operaciones' => $ops, 'catalogo_gastos' => \Crm\Comex\LandedCost::GASTOS_CATALOGO];
});
