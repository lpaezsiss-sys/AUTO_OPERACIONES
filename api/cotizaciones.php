<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    $user = \Crm\Auth::requireUser();
    $method = \Crm\Http::method();
    $id = \Crm\Http::idParam();
    if ($method === 'GET' && $id > 0) {
        return \Crm\Cotizaciones::show($id);
    }
    if ($method === 'GET' && isset($_GET['proximo'])) {
        return \Crm\Cotizaciones::proximoFolio();
    }
    if ($method === 'GET') {
        return \Crm\Cotizaciones::index();
    }
    if ($method === 'POST') {
        return \Crm\Cotizaciones::store(\Crm\Http::body(), $user);
    }
    if ($method === 'PUT' || $method === 'PATCH') {
        $body = \Crm\Http::body();
        $action = isset($_GET['action']) ? (string) $_GET['action'] : (isset($body['action']) ? (string) $body['action'] : '');
        if ($action === 'folio' || $action === 'actualizar-numero' || $action === 'actualizar_numero') {
            \Crm\Auth::requireAdmin();
            return \Crm\Cotizaciones::actualizarFolio($id, $body);
        }
        return \Crm\Cotizaciones::update($id, $body, $user);
    }
    if ($method === 'DELETE') {
        return \Crm\Cotizaciones::destroy($id);
    }
    \Crm\Http::fail('Método no permitido', 405);
    return array();
});
