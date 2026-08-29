<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$user = \Crm\Auth::user();
if ($user === null) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => false, 'error' => 'No autenticado'));
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => false, 'error' => 'Debe indicar la cotización'));
    exit;
}

try {
    $pack = \Crm\Cotizaciones::show($id);
    $cot = $pack['cotizacion'];
    $bin = \Crm\CotizacionPdf::generar($cot);
    $folio = preg_replace('/[^A-Za-z0-9\-]+/', '_', (string) $cot['folio']);
    header('Content-Type: application/pdf');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="' . $folio . '.pdf"');
    header('Content-Length: ' . strlen($bin));
    header('Cache-Control: private, no-store');
    echo $bin;
} catch (\Crm\ApiException $e) {
    http_response_code((int) $e->status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
}
exit;
