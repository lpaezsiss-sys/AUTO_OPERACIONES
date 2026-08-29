<?php

declare(strict_types=1);

/**
 * Cotizador: búsqueda de productos e ingreso de cotización.
 * PHP 7.4 — GET ?action=buscar_producto | POST ?action=guardar
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require dirname(__DIR__) . '/includes/bootstrap.php';
\Crm\Http::noCacheHeaders();

/**
 * @param array $payload
 * @param int $code
 * @return void
 */
function crm_cotizador_json(array $payload, $code = 200)
{
    if (!array_key_exists('success', $payload)) {
        $payload['success'] = !empty($payload['ok']);
    }
    http_response_code((int) $code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$action = isset($_GET['action']) ? trim((string) $_GET['action']) : '';
$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';

$user = \Crm\Auth::user();
if ($user === null) {
    crm_cotizador_json(\Crm\Http::payloadFail('No autenticado'), 401);
}

try {
    if ($method === 'GET' && $action === 'buscar_producto') {
        crm_buscar_producto();
    } elseif ($method === 'POST' && $action === 'guardar') {
        crm_guardar_cotizacion($user);
    } else {
        crm_cotizador_json(\Crm\Http::payloadFail('Acción no válida'), 400);
    }
} catch (\Throwable $e) {
    try {
        $pdo = crm_pdo();
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (\Throwable $ignored) {
    }
    $msg = $e instanceof PDOException
        ? ('Error de base de datos: ' . $e->getMessage())
        : $e->getMessage();
    crm_cotizador_json(\Crm\Http::payloadFail($msg), 500);
}

/**
 * GET ?action=buscar_producto&q=
 * Catálogo CRM + SQLite de inventario (alta stock 0 si el SKU no existe en productos).
 *
 * @return void
 */
function crm_buscar_producto()
{
    $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
    if ($q === '') {
        crm_cotizador_json(array('ok' => true, 'productos' => array()));
    }

    $rows = \Crm\Productos::buscarParaCotizador($q, 20);
    crm_cotizador_json(array('ok' => true, 'productos' => $rows));
}

/**
 * POST ?action=guardar
 * JSON en php://input. Transacción: correlativo + encabezado + ítems.
 *
 * @param array $user
 * @return void
 */
function crm_guardar_cotizacion(array $user)
{
    $raw = file_get_contents('php://input');
    $raw = is_string($raw) ? $raw : '';
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        crm_cotizador_json(\Crm\Http::payloadFail('JSON inválido'), 400);
    }

    $empresaId = isset($data['empresa_id']) ? (int) $data['empresa_id'] : 0;
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : array();
    $notas = isset($data['notas']) ? trim((string) $data['notas']) : '';
    $descuento = crm_float(isset($data['descuento']) ? $data['descuento'] : 0, 0);
    $contactoId = isset($data['contacto_id']) ? (int) $data['contacto_id'] : 0;
    $oportunidadId = isset($data['oportunidad_id']) ? (int) $data['oportunidad_id'] : 0;
    $estado = isset($data['estado']) ? trim((string) $data['estado']) : 'borrador';
    $estadosOk = array('borrador' => true, 'enviada' => true, 'aceptada' => true, 'rechazada' => true, 'vencida' => true);
    $estado = isset($estadosOk[$estado]) ? $estado : 'borrador';
    $listaPrecioId = isset($data['lista_precio_id']) ? (int) $data['lista_precio_id'] : 0;
    $listaPrecioIdSql = null;
    if ($listaPrecioId > 0) {
        if (\Crm\ListasPrecios::obtener($listaPrecioId) === null) {
            crm_cotizador_json(\Crm\Http::payloadFail('Lista de precios no encontrada'), 404);
        }
        $listaPrecioIdSql = $listaPrecioId;
    }

    if ($empresaId <= 0) {
        crm_cotizador_json(\Crm\Http::payloadFail('Debe indicar la empresa'), 400);
    }
    if (empty($items)) {
        crm_cotizador_json(\Crm\Http::payloadFail('La cotización requiere al menos un ítem'), 400);
    }
    if ($descuento < 0) {
        $descuento = 0.0;
    }

    $pdo = crm_pdo();
    $emp = $pdo->prepare('SELECT id FROM crm_empresas WHERE id = ? LIMIT 1');
    $emp->execute(array($empresaId));
    if (!$emp->fetch(PDO::FETCH_ASSOC)) {
        crm_cotizador_json(\Crm\Http::payloadFail('Empresa no encontrada'), 404);
    }

    if ($contactoId > 0) {
        $cto = $pdo->prepare('SELECT id, empresa_id FROM crm_contactos WHERE id = ? LIMIT 1');
        $cto->execute(array($contactoId));
        $ctoRow = $cto->fetch(PDO::FETCH_ASSOC);
        if (!$ctoRow) {
            crm_cotizador_json(\Crm\Http::payloadFail('Contacto no encontrado'), 404);
        }
        if ((int) $ctoRow['empresa_id'] !== $empresaId) {
            crm_cotizador_json(\Crm\Http::payloadFail('El contacto no pertenece a la empresa seleccionada'), 400);
        }
    }

    $ivaPct = crm_iva_pct();
    $now = crm_now();
    $fechaEmision = isset($data['fecha_emision']) && $data['fecha_emision'] !== ''
        ? (string) $data['fecha_emision']
        : date('Y-m-d');
    $fechaValidez = isset($data['fecha_validez']) && $data['fecha_validez'] !== ''
        ? (string) $data['fecha_validez']
        : null;
    $ejecutivoId = isset($user['id']) ? (int) $user['id'] : null;

    $pdo->beginTransaction();
    try {
        $comercial = \Crm\Cotizaciones::condicionesComerciales($data);
        $folio = \Crm\Codes::next('crm_cotizaciones', 'folio', 'COT');

        $insCot = $pdo->prepare(
            'INSERT INTO crm_cotizaciones
                (folio, empresa_id, contacto_id, oportunidad_id, ejecutivo_id, vendedor_id, lista_precio_id, estado, fecha_emision, fecha_validez, validez_oferta, moneda, condiciones_pago, plazo_entrega, lugar_entrega, subtotal, descuento, iva, total, notas, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, ?, ?)'
        );
        $vendedorIdPre = \Crm\Comisiones::resolverVendedorId($pdo, $data, $ejecutivoId ? $ejecutivoId : 0);
        $insCot->execute(array(
            $folio,
            $empresaId,
            $contactoId > 0 ? $contactoId : null,
            $oportunidadId > 0 ? $oportunidadId : null,
            $ejecutivoId,
            $vendedorIdPre > 0 ? $vendedorIdPre : null,
            $listaPrecioIdSql,
            $estado,
            $fechaEmision,
            $fechaValidez,
            $comercial['validez_oferta'],
            $comercial['moneda'],
            $comercial['condiciones_pago'],
            $comercial['plazo_entrega'],
            $comercial['lugar_entrega'],
            $notas !== '' ? $notas : null,
            $now,
        ));
        $cotizacionId = (int) $pdo->lastInsertId();

        $insItem = $pdo->prepare(
            'INSERT INTO crm_cotizacion_items
                (cotizacion_id, tipo_item, es_a_pedido, producto_id, marca_id, marca_nombre, codigo, descripcion, descripcion_detallada, imagen_url, cantidad, precio_unitario, costo_unitario, descuento_pct, subtotal, stock_al_cotizar)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $subtotal = 0.0;
        $i = 0;
        $totalItems = count($items);
        while ($i < $totalItems) {
            $rawItem = $items[$i];
            $i++;
            if (!is_array($rawItem)) {
                continue;
            }
            $item = \Crm\Cotizaciones::normalizarItem($pdo, $rawItem);
            $subtotal += $item['subtotal'];
            $insItem->execute(array(
                $cotizacionId,
                $item['tipo_item'],
                $item['es_a_pedido'],
                $item['producto_id'],
                $item['marca_id'],
                $item['marca_nombre'],
                $item['codigo'],
                $item['descripcion'],
                $item['descripcion_detallada'],
                $item['imagen_url'],
                $item['cantidad'],
                $item['precio_unitario'],
                $item['costo_unitario'],
                $item['descuento_pct'],
                $item['subtotal'],
                $item['stock_al_cotizar'],
            ));
        }

        $descuento = $descuento > $subtotal ? $subtotal : $descuento;
        $neto = $subtotal - $descuento;
        $iva = round($neto * ($ivaPct / 100), 2);
        $total = round($neto + $iva, 2);

        $upd = $pdo->prepare(
            'UPDATE crm_cotizaciones SET subtotal = ?, descuento = ?, iva = ?, total = ?, vendedor_id = ?, updated_at = ? WHERE id = ?'
        );
        $upd->execute(array($subtotal, $descuento, $iva, $total, $vendedorIdPre > 0 ? $vendedorIdPre : null, $now, $cotizacionId));

        \Crm\Comisiones::sincronizarConCotizacion($pdo, $cotizacionId, $vendedorIdPre, $neto, $estado);
        \Crm\Marcas::sincronizarCotizacion($pdo, $cotizacionId, $data);

        $pdo->commit();
    } catch (\Crm\ApiException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        crm_cotizador_json(\Crm\Http::payloadFail($e->getMessage()), (int) $e->status);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $msg = $e->getMessage();
        if ($e instanceof PDOException) {
            $msg = 'Error de base de datos: ' . $msg;
        }
        $code = (strpos($msg, 'no encontrado') !== false || strpos($msg, 'requiere') !== false || strpos($msg, 'cantidad') !== false)
            ? 400
            : 500;
        crm_cotizador_json(\Crm\Http::payloadFail($msg), $code);
    }

    crm_cotizador_json(array(
        'ok' => true,
        'id' => $cotizacionId,
        'folio' => $folio,
        'subtotal' => $subtotal,
        'descuento' => $descuento,
        'iva' => $iva,
        'total' => $total,
        'iva_pct' => $ivaPct,
    ));
}
