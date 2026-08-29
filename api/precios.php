<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    \Crm\Auth::requireUser();
    if (\Crm\Http::method() !== 'GET') {
        \Crm\Http::fail('Método no permitido', 405);
    }
    $empresaId = crm_int(isset($_GET['empresa_id']) ? $_GET['empresa_id'] : 0, 0);
    $listaId = crm_int(isset($_GET['lista_precio_id']) ? $_GET['lista_precio_id'] : 0, 0);
    $productoId = crm_int(isset($_GET['producto_id']) ? $_GET['producto_id'] : 0, 0);
    $idsRaw = isset($_GET['producto_ids']) ? (string) $_GET['producto_ids'] : '';
    if ($productoId > 0) {
        return array('precio' => \Crm\Precios::resolver($empresaId, $productoId, $listaId));
    }
    if ($idsRaw !== '') {
        $out = array();
        foreach (explode(',', $idsRaw) as $part) {
            $pid = crm_int($part, 0);
            if ($pid <= 0) {
                continue;
            }
            $out[] = \Crm\Precios::resolver($empresaId, $pid, $listaId);
        }
        return array('precios' => $out);
    }
    \Crm\Http::fail('Debe indicar producto_id');
    return array();
});
