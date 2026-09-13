<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$method = \Crm\Http::method();
$id = (int) ($_GET['id'] ?? 0);
$stream = isset($_GET['file']) || isset($_GET['preview']) || isset($_GET['download']);

if ($method === 'GET' && $id > 0 && $stream) {
    try {
        \Crm\Comex\Documentos::stream($id, isset($_GET['download']));
    } catch (\Crm\ApiException $e) {
        \Crm\Http::jsonHeaders();
        \Crm\Http::json(\Crm\Http::payloadFail($e->getMessage()), $e->status);
    }
}

\Crm\Http::handle(static function () use ($method): array {
    $body = \Crm\Http::body();
    $operacionId = (int) ($_GET['operacion_id'] ?? $body['operacion_id'] ?? $_POST['operacion_id'] ?? 0);
    $id = (int) ($_GET['id'] ?? $body['id'] ?? $_POST['id'] ?? 0);

    $action = strtolower((string) ($body['action'] ?? $_POST['action'] ?? $_GET['action'] ?? ''));

    if ($method === 'DELETE' || $action === 'eliminar') {
        if ($id <= 0) {
            \Crm\Http::fail('id requerido', 400);
        }
        $out = \Crm\Comex\Documentos::eliminar($id);
        $opId = (int) $out['operacion_id'];
        return $out + ['repositorio' => \Crm\Comex\Documentos::paraOperacion($opId)];
    }

    if ($method === 'POST') {
        $opId = (int) ($_POST['operacion_id'] ?? $body['operacion_id'] ?? 0);
        $tipo = (string) ($_POST['tipo'] ?? $body['tipo'] ?? '');
        $usuario = (string) ($_POST['usuario'] ?? $body['usuario'] ?? 'COMEX');
        $file = $_FILES['archivo'] ?? null;
        if (!is_array($file)) {
            \Crm\Http::fail('archivo requerido (multipart)', 400);
        }
        return [
            'documento' => \Crm\Comex\Documentos::subir($opId, $file, $tipo, $usuario),
            'repositorio' => \Crm\Comex\Documentos::paraOperacion($opId),
        ];
    }

    if ($operacionId > 0) {
        return \Crm\Comex\Documentos::paraOperacion($operacionId);
    }
    if ($id > 0) {
        $row = \Crm\Comex\Documentos::porId($id);
        if ($row === null) {
            \Crm\Http::fail('Documento no encontrado', 404);
        }
        return ['documento' => $row];
    }

    return ['tipos' => \Crm\Comex\Documentos::TIPOS];
});
