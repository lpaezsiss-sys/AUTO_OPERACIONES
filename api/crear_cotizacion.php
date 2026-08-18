<?php

declare(strict_types=1);

/**
 * Cotizador: búsqueda de productos e ingreso de cotización.
 * PHP 7.4 — GET ?action=buscar_producto | POST ?action=guardar
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require dirname(__DIR__) . '/includes/bootstrap.php';

$action = isset($_GET['action']) ? trim((string) $_GET['action']) : '';
$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';

$user = \Crm\Auth::user();
if ($user === null) {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'error' => 'No autenticado'));
    exit;
}

try {
    if ($method === 'GET' && $action === 'buscar_producto') {
        crm_buscar_producto();
    } elseif ($method === 'POST' && $action === 'guardar') {
        crm_guardar_cotizacion($user);
    } else {
        http_response_code(400);
        echo json_encode(array('ok' => false, 'error' => 'Acción no válida'));
    }
} catch (PDOException $e) {
    $pdo = crm_pdo();
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    $out = array('ok' => false, 'error' => 'Error de base de datos');
    if (crm_debug()) {
        $out['detail'] = $e->getMessage();
    }
    echo json_encode($out);
}

/**
 * GET ?action=buscar_producto&q=
 * LIKE sobre nombre y SKU (codigo) en la tabla de inventario `productos`.
 *
 * @return void
 */
function crm_buscar_producto()
{
    $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
    if ($q === '') {
        echo json_encode(array('ok' => true, 'productos' => array()));
        exit;
    }

    $like = '%' . $q . '%';
    $colImg = \Crm\ItemImagen::columnaInventario(crm_pdo());
    $sql = 'SELECT id, codigo, codigo AS sku, nombre, descripcion, stock, precio_unitario, unidad';
    if ($colImg !== '') {
        $sql .= ', ' . $colImg;
    }
    $sql .= ' FROM productos
            WHERE activo = 1
              AND (nombre LIKE ? OR codigo LIKE ?)
            ORDER BY nombre ASC
            LIMIT 20';
    $stmt = crm_pdo()->prepare($sql);
    $stmt->execute(array($like, $like));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($rows)) {
        $rows = array();
    }
    foreach ($rows as &$row) {
        $row = \Crm\ItemImagen::anexarAProducto($row);
    }
    unset($row);

    echo json_encode(array('ok' => true, 'productos' => $rows));
    exit;
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
        http_response_code(400);
        echo json_encode(array('ok' => false, 'error' => 'JSON inválido'));
        exit;
    }

    $empresaId = isset($data['empresa_id']) ? (int) $data['empresa_id'] : 0;
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : array();
    $notas = isset($data['notas']) ? trim((string) $data['notas']) : '';
    $descuento = isset($data['descuento']) ? (float) $data['descuento'] : 0.0;
    $contactoId = isset($data['contacto_id']) ? (int) $data['contacto_id'] : 0;
    $oportunidadId = isset($data['oportunidad_id']) ? (int) $data['oportunidad_id'] : 0;
    $estado = isset($data['estado']) ? trim((string) $data['estado']) : 'borrador';
    $estadosOk = array('borrador' => true, 'enviada' => true, 'aceptada' => true, 'rechazada' => true, 'vencida' => true);
    $estado = isset($estadosOk[$estado]) ? $estado : 'borrador';
    $listaPrecioId = isset($data['lista_precio_id']) ? (int) $data['lista_precio_id'] : 0;
    $listaPrecioIdSql = null;
    if ($listaPrecioId > 0) {
        if (\Crm\ListasPrecios::obtener($listaPrecioId) === null) {
            http_response_code(404);
            echo json_encode(array('ok' => false, 'error' => 'Lista de precios no encontrada'));
            exit;
        }
        $listaPrecioIdSql = $listaPrecioId;
    }

    if ($empresaId <= 0) {
        http_response_code(400);
        echo json_encode(array('ok' => false, 'error' => 'Debe indicar la empresa'));
        exit;
    }
    if (empty($items)) {
        http_response_code(400);
        echo json_encode(array('ok' => false, 'error' => 'La cotización requiere al menos un ítem'));
        exit;
    }
    if ($descuento < 0) {
        $descuento = 0.0;
    }

    $pdo = crm_pdo();
    $emp = $pdo->prepare('SELECT id FROM crm_empresas WHERE id = ? LIMIT 1');
    $emp->execute(array($empresaId));
    if (!$emp->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode(array('ok' => false, 'error' => 'Empresa no encontrada'));
        exit;
    }

    if ($contactoId > 0) {
        $cto = $pdo->prepare('SELECT id, empresa_id FROM crm_contactos WHERE id = ? LIMIT 1');
        $cto->execute(array($contactoId));
        $ctoRow = $cto->fetch(PDO::FETCH_ASSOC);
        if (!$ctoRow) {
            http_response_code(404);
            echo json_encode(array('ok' => false, 'error' => 'Contacto no encontrado'));
            exit;
        }
        if ((int) $ctoRow['empresa_id'] !== $empresaId) {
            http_response_code(400);
            echo json_encode(array('ok' => false, 'error' => 'El contacto no pertenece a la empresa seleccionada'));
            exit;
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
        $year = date('Y');
        $likeFolio = 'COT-' . $year . '-%';
        $last = $pdo->prepare(
            'SELECT folio FROM crm_cotizaciones WHERE folio LIKE ? ORDER BY folio DESC LIMIT 1'
        );
        $last->execute(array($likeFolio));
        $prev = $last->fetchColumn();
        $n = 1;
        if (is_string($prev) && preg_match('/-(\d+)$/', $prev, $m)) {
            $n = ((int) $m[1]) + 1;
        }
        $folio = 'COT-' . $year . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);

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
        http_response_code((int) $e->status);
        echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $msg = $e->getMessage();
        $code = (strpos($msg, 'no encontrado') !== false || strpos($msg, 'requiere') !== false || strpos($msg, 'cantidad') !== false)
            ? 400
            : 500;
        http_response_code($code);
        echo json_encode(array('ok' => false, 'error' => $msg));
        exit;
    }

    echo json_encode(array(
        'ok' => true,
        'id' => $cotizacionId,
        'folio' => $folio,
        'subtotal' => $subtotal,
        'descuento' => $descuento,
        'iva' => $iva,
        'total' => $total,
        'iva_pct' => $ivaPct,
    ));
    exit;
}
