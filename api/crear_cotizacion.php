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
    $sql = 'SELECT id, codigo, codigo AS sku, nombre, descripcion, stock, precio_unitario, unidad
            FROM productos
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

    $ivaPct = crm_iva_pct();
    $now = crm_now();
    $fechaEmision = isset($data['fecha_emision']) && $data['fecha_emision'] !== ''
        ? (string) $data['fecha_emision']
        : date('Y-m-d');
    $fechaValidez = isset($data['fecha_validez']) && $data['fecha_validez'] !== ''
        ? (string) $data['fecha_validez']
        : null;
    $ejecutivoId = isset($user['id']) ? (int) $user['id'] : null;

    $findProd = $pdo->prepare(
        'SELECT id, codigo, nombre, stock, precio_unitario FROM productos WHERE id = ? LIMIT 1'
    );

    $pdo->beginTransaction();
    try {
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
                (folio, empresa_id, contacto_id, oportunidad_id, ejecutivo_id, estado, fecha_emision, fecha_validez, subtotal, descuento, iva, total, notas, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, ?, ?)'
        );
        $insCot->execute(array(
            $folio,
            $empresaId,
            $contactoId > 0 ? $contactoId : null,
            $oportunidadId > 0 ? $oportunidadId : null,
            $ejecutivoId,
            $estado,
            $fechaEmision,
            $fechaValidez,
            $notas !== '' ? $notas : null,
            $now,
        ));
        $cotizacionId = (int) $pdo->lastInsertId();

        $insItem = $pdo->prepare(
            'INSERT INTO crm_cotizacion_items
                (cotizacion_id, producto_id, codigo, descripcion, cantidad, precio_unitario, descuento_pct, subtotal, stock_al_cotizar)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
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

            $productoId = isset($rawItem['producto_id']) ? (int) $rawItem['producto_id'] : 0;
            $codigo = isset($rawItem['codigo']) ? trim((string) $rawItem['codigo']) : '';
            $descripcion = isset($rawItem['descripcion']) ? trim((string) $rawItem['descripcion']) : '';
            $cantidad = isset($rawItem['cantidad']) ? (float) $rawItem['cantidad'] : 1.0;
            $precio = isset($rawItem['precio_unitario']) ? (float) $rawItem['precio_unitario'] : 0.0;
            $descPct = isset($rawItem['descuento_pct']) ? (float) $rawItem['descuento_pct'] : 0.0;
            $stockSnap = null;

            if ($productoId > 0) {
                $findProd->execute(array($productoId));
                $prod = $findProd->fetch(PDO::FETCH_ASSOC);
                if (!$prod) {
                    throw new RuntimeException('Producto de inventario no encontrado: id ' . $productoId);
                }
                $codigo = $codigo !== '' ? $codigo : (string) $prod['codigo'];
                $descripcion = $descripcion !== '' ? $descripcion : (string) $prod['nombre'];
                $precio = $precio > 0 ? $precio : (float) $prod['precio_unitario'];
                $stockSnap = (float) $prod['stock'];
            }

            if ($codigo === '' || $descripcion === '') {
                throw new RuntimeException('Cada ítem requiere código y descripción');
            }
            if ($cantidad <= 0) {
                throw new RuntimeException('La cantidad debe ser mayor a 0');
            }
            $descPct = $descPct < 0 ? 0.0 : $descPct;
            $descPct = $descPct > 100 ? 100.0 : $descPct;
            $line = round($cantidad * $precio * (1 - ($descPct / 100)), 2);
            $subtotal += $line;

            $insItem->execute(array(
                $cotizacionId,
                $productoId > 0 ? $productoId : null,
                $codigo,
                $descripcion,
                $cantidad,
                $precio,
                $descPct,
                $line,
                $stockSnap,
            ));
        }

        $descuento = $descuento > $subtotal ? $subtotal : $descuento;
        $neto = $subtotal - $descuento;
        $iva = round($neto * ($ivaPct / 100), 2);
        $total = round($neto + $iva, 2);

        $upd = $pdo->prepare(
            'UPDATE crm_cotizaciones SET subtotal = ?, descuento = ?, iva = ?, total = ?, updated_at = ? WHERE id = ?'
        );
        $upd->execute(array($subtotal, $descuento, $iva, $total, $now, $cotizacionId));

        $pdo->commit();
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
