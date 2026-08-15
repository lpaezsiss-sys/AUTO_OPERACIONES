<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Cotizaciones
{
    /**
     * @return array
     */
    public static function index()
    {
        $estado = crm_str(isset($_GET['estado']) ? $_GET['estado'] : '', 40);
        $sql = 'SELECT c.*, e.razon_social, e.rut
                FROM crm_cotizaciones c
                INNER JOIN crm_empresas e ON e.id = c.empresa_id
                WHERE 1=1';
        $params = array();
        if ($estado !== '') {
            $sql .= ' AND c.estado = ?';
            $params[] = $estado;
        }
        $sql .= ' ORDER BY c.created_at DESC';
        $stmt = crm_pdo()->prepare($sql);
        $stmt->execute($params);
        return array('cotizaciones' => $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param int $id
     * @return array
     */
    public static function show($id)
    {
        $pdo = crm_pdo();
        $stmt = $pdo->prepare(
            'SELECT c.*, e.razon_social, e.rut, e.direccion, e.giro,
                    v.nombre_completo AS vendedor_nombre, v.email AS vendedor_email, v.telefono AS vendedor_telefono
             FROM crm_cotizaciones c
             INNER JOIN crm_empresas e ON e.id = c.empresa_id
             LEFT JOIN crm_vendedores v ON v.id = c.vendedor_id
             WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute(array((int) $id));
        $cot = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cot) {
            Http::fail('Cotización no encontrada', 404);
        }
        $items = $pdo->prepare(
            'SELECT i.*, p.stock AS stock_actual, p.umbral_stock
             FROM crm_cotizacion_items i
             LEFT JOIN productos p ON p.id = i.producto_id
             WHERE i.cotizacion_id = ?
             ORDER BY i.id ASC'
        );
        $items->execute(array((int) $id));
        $rows = $items->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $stock = $row['stock_actual'] !== null ? (float) $row['stock_actual'] : null;
            $row['alerta_stock'] = $stock !== null && $stock < (float) $row['cantidad'];
        }
        unset($row);
        $cot['items'] = $rows;
        $cot['iva_pct'] = crm_iva_pct();
        $cot['emisora'] = ConfiguracionEmpresa::obtener($pdo);
        $vendId = crm_int(isset($cot['vendedor_id']) ? $cot['vendedor_id'] : 0, 0);
        $cot['vendedor'] = $vendId > 0 ? Vendedores::obtener($pdo, $vendId) : null;
        return array('cotizacion' => $cot);
    }

    /**
     * @param array $body
     * @param array $user
     * @return array
     */
    public static function store(array $body, array $user)
    {
        return self::persist(0, $body, $user);
    }

    /**
     * @param int $id
     * @param array $body
     * @param array $user
     * @return array
     */
    public static function update($id, array $body, array $user)
    {
        return self::persist((int) $id, $body, $user);
    }

    /**
     * @param int $id
     * @param array $body
     * @param array $user
     * @return array
     */
    private static function persist($id, array $body, array $user)
    {
        $pdo = crm_pdo();
        $empresaId = crm_int(isset($body['empresa_id']) ? $body['empresa_id'] : 0, 0);
        if ($empresaId <= 0) {
            Http::fail('Debe indicar la empresa');
        }
        Codes::requireEmpresa($pdo, $empresaId);
        $estado = Catalog::inList(isset($body['estado']) ? (string) $body['estado'] : 'borrador', Catalog::cotizacionEstados(), 'estado');
        $itemsIn = isset($body['items']) && is_array($body['items']) ? $body['items'] : array();
        if (count($itemsIn) === 0) {
            Http::fail('La cotización requiere al menos un ítem');
        }

        $descuentoGlobal = crm_float(isset($body['descuento']) ? $body['descuento'] : 0, 0);
        if ($descuentoGlobal < 0) {
            $descuentoGlobal = 0;
        }

        $contactoId = crm_int(isset($body['contacto_id']) ? $body['contacto_id'] : 0, 0);
        $oppId = crm_int(isset($body['oportunidad_id']) ? $body['oportunidad_id'] : 0, 0);
        $fechaEmision = crm_str(isset($body['fecha_emision']) ? $body['fecha_emision'] : date('Y-m-d'), 10);
        if ($fechaEmision === '') {
            $fechaEmision = date('Y-m-d');
        }
        $fechaValidez = crm_str(isset($body['fecha_validez']) ? $body['fecha_validez'] : '', 10);
        $notas = crm_str(isset($body['notas']) ? $body['notas'] : '', 4000) ?: null;
        $ejecutivoId = crm_int(isset($body['ejecutivo_id']) ? $body['ejecutivo_id'] : $user['id'], (int) $user['id']);
        $vendedorId = 0;

        $pdo->beginTransaction();
        try {
            $vendedorId = Comisiones::resolverVendedorId($pdo, $body, $ejecutivoId);
            if ($id > 0) {
                $exists = $pdo->prepare('SELECT id FROM crm_cotizaciones WHERE id = ? LIMIT 1');
                $exists->execute(array($id));
                if (!$exists->fetchColumn()) {
                    Http::fail('Cotización no encontrada', 404);
                }
            } else {
                $folio = Codes::next('crm_cotizaciones', 'folio', 'COT');
                $ins = $pdo->prepare(
                    'INSERT INTO crm_cotizaciones (folio, empresa_id, contacto_id, oportunidad_id, ejecutivo_id, vendedor_id, estado, fecha_emision, fecha_validez, subtotal, descuento, iva, total, notas, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, ?, ?)'
                );
                $ins->execute(array(
                    $folio,
                    $empresaId,
                    $contactoId > 0 ? $contactoId : null,
                    $oppId > 0 ? $oppId : null,
                    $ejecutivoId,
                    $vendedorId > 0 ? $vendedorId : null,
                    $estado,
                    $fechaEmision,
                    $fechaValidez !== '' ? $fechaValidez : null,
                    $notas,
                    crm_now(),
                ));
                $id = (int) $pdo->lastInsertId();
            }

            $del = $pdo->prepare('DELETE FROM crm_cotizacion_items WHERE cotizacion_id = ?');
            $del->execute(array($id));

            $subtotal = 0.0;
            $insItem = $pdo->prepare(
                'INSERT INTO crm_cotizacion_items (cotizacion_id, producto_id, codigo, descripcion, cantidad, precio_unitario, descuento_pct, subtotal, stock_al_cotizar)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $findProd = $pdo->prepare(
                'SELECT id, codigo, nombre, stock, precio_unitario FROM productos WHERE id = ? LIMIT 1'
            );

            foreach ($itemsIn as $raw) {
                if (!is_array($raw)) {
                    continue;
                }
                $productoId = crm_int(isset($raw['producto_id']) ? $raw['producto_id'] : 0, 0);
                $codigo = crm_str(isset($raw['codigo']) ? $raw['codigo'] : '', 50);
                $descripcion = crm_str(isset($raw['descripcion']) ? $raw['descripcion'] : '', 300);
                $cantidad = crm_float(isset($raw['cantidad']) ? $raw['cantidad'] : 1, 1);
                $precio = crm_float(isset($raw['precio_unitario']) ? $raw['precio_unitario'] : 0, 0);
                $descPct = crm_float(isset($raw['descuento_pct']) ? $raw['descuento_pct'] : 0, 0);
                $stockSnap = null;

                if ($productoId > 0) {
                    $findProd->execute(array($productoId));
                    $prod = $findProd->fetch(PDO::FETCH_ASSOC);
                    if (!$prod) {
                        Http::fail('Producto de inventario no encontrado: id ' . $productoId);
                    }
                    if ($codigo === '') {
                        $codigo = (string) $prod['codigo'];
                    }
                    if ($descripcion === '') {
                        $descripcion = (string) $prod['nombre'];
                    }
                    if ($precio <= 0) {
                        $precio = (float) $prod['precio_unitario'];
                    }
                    $stockSnap = (float) $prod['stock'];
                }
                if ($codigo === '' || $descripcion === '') {
                    Http::fail('Cada ítem requiere código y descripción');
                }
                if ($cantidad <= 0) {
                    Http::fail('La cantidad debe ser mayor a 0');
                }
                if ($descPct < 0) {
                    $descPct = 0;
                }
                if ($descPct > 100) {
                    $descPct = 100;
                }
                $line = round($cantidad * $precio * (1 - ($descPct / 100)), 2);
                $subtotal += $line;
                $insItem->execute(array(
                    $id,
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

            if ($descuentoGlobal > $subtotal) {
                $descuentoGlobal = $subtotal;
            }
            $neto = $subtotal - $descuentoGlobal;
            $iva = round($neto * (crm_iva_pct() / 100), 2);
            $total = round($neto + $iva, 2);

            $upd = $pdo->prepare(
                'UPDATE crm_cotizaciones
                 SET empresa_id=?, contacto_id=?, oportunidad_id=?, ejecutivo_id=?, vendedor_id=?, estado=?, fecha_emision=?, fecha_validez=?, subtotal=?, descuento=?, iva=?, total=?, notas=?, updated_at=?
                 WHERE id=?'
            );
            $upd->execute(array(
                $empresaId,
                $contactoId > 0 ? $contactoId : null,
                $oppId > 0 ? $oppId : null,
                $ejecutivoId,
                $vendedorId > 0 ? $vendedorId : null,
                $estado,
                $fechaEmision,
                $fechaValidez !== '' ? $fechaValidez : null,
                $subtotal,
                $descuentoGlobal,
                $iva,
                $total,
                $notas,
                crm_now(),
                $id,
            ));

            Comisiones::sincronizarConCotizacion($pdo, $id, $vendedorId, $neto, $estado);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return self::show($id);
    }
}
