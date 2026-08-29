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

try {
    $bin = \Crm\Manual::generarPdf();
    $nombre = 'CRM_LPAEZsis_Manual_Usuario_' . date('Y-m-d') . '.pdf';
    header('Content-Type: application/pdf');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Content-Length: ' . strlen($bin));
    header('Cache-Control: private, no-store');
    echo $bin;
} catch (\Crm\ApiException $e) {
    http_response_code((int) $e->status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    $payload = array('ok' => false, 'error' => 'No se pudo generar el PDF del manual');
    if (!crm_is_production()) {
        $payload['detail'] = $e->getMessage();
    }
    echo json_encode($payload);
}
exit;
