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
     * Folio correlativo provisorio. No reserva el número.
     *
     * @return array
     */
    public static function proximoFolio()
    {
        return array(
            'proximo_folio' => Codes::peek('crm_cotizaciones', 'folio', 'COT'),
        );
    }

    /**
     * @param int $id
     * @return array
     */
    public static function show($id)
    {
        $pdo = crm_pdo();
        $stmt = $pdo->prepare(
            'SELECT c.*, e.razon_social, e.rut, e.direccion, e.giro, e.comuna,
                    ct.nombre AS contacto_nombre, ct.apellido AS contacto_apellido,
                    ct.email AS contacto_email, ct.telefono AS contacto_telefono,
                    v.nombre_completo AS vendedor_nombre, v.email AS vendedor_email, v.telefono AS vendedor_telefono
             FROM crm_cotizaciones c
             INNER JOIN crm_empresas e ON e.id = c.empresa_id
             LEFT JOIN crm_contactos ct ON ct.id = c.contacto_id
             LEFT JOIN crm_vendedores v ON v.id = c.vendedor_id
             WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute(array((int) $id));
        $cot = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cot) {
            Http::fail('Cotización no encontrada', 404);
        }
        $items = $pdo->prepare(
            'SELECT i.*, p.stock AS stock_actual, p.umbral_stock, p.unidad AS producto_unidad
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
            $tipo = isset($row['tipo_item']) ? (string) $row['tipo_item'] : 'producto';
            $un = isset($row['producto_unidad']) ? trim((string) $row['producto_unidad']) : '';
            if ($un === '') {
                $un = $tipo === 'servicio' ? 'GL' : 'UN';
            }
            $row['unidad'] = $un;
            $row['es_a_pedido'] = !empty($row['es_a_pedido']) || $tipo === 'a_pedido' ? 1 : 0;
            $row['marca_id'] = isset($row['marca_id']) && $row['marca_id'] !== '' ? (int) $row['marca_id'] : null;
            $row['marca_nombre'] = isset($row['marca_nombre']) ? (string) $row['marca_nombre'] : '';
            $row['costo_unitario'] = isset($row['costo_unitario']) ? (float) $row['costo_unitario'] : 0.0;
            $row['descripcion_detallada'] = isset($row['descripcion_detallada']) ? (string) $row['descripcion_detallada'] : '';
            $row['imagen_url'] = isset($row['imagen_url']) ? (string) $row['imagen_url'] : '';
        }
        unset($row);
        $cot['items'] = $rows;
        $cot['iva_pct'] = crm_iva_pct();
        $cot['emisora'] = ConfiguracionEmpresa::obtener($pdo);
        $vendId = crm_int(isset($cot['vendedor_id']) ? $cot['vendedor_id'] : 0, 0);
        $cot['vendedor'] = $vendId > 0 ? Vendedores::obtener($pdo, $vendId) : null;
        $cot['marca_ids'] = Marcas::idsDeCotizacion((int) $id, $pdo);
        $cot['marcas'] = Marcas::paraPdf((int) $id, $pdo);
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
        if ($contactoId > 0) {
            $ctoStmt = $pdo->prepare('SELECT id, empresa_id FROM crm_contactos WHERE id = ? LIMIT 1');
            $ctoStmt->execute(array($contactoId));
            $ctoRow = $ctoStmt->fetch(PDO::FETCH_ASSOC);
            if (!$ctoRow) {
                Http::fail('Contacto no encontrado', 404);
            }
            if ((int) $ctoRow['empresa_id'] !== $empresaId) {
                Http::fail('El contacto no pertenece a la empresa seleccionada');
            }
        }
        $oppId = crm_int(isset($body['oportunidad_id']) ? $body['oportunidad_id'] : 0, 0);
        $fechaEmision = crm_str(isset($body['fecha_emision']) ? $body['fecha_emision'] : date('Y-m-d'), 10);
        if ($fechaEmision === '') {
            $fechaEmision = date('Y-m-d');
        }
        $fechaValidez = crm_str(isset($body['fecha_validez']) ? $body['fecha_validez'] : '', 10);
        $comercial = self::condicionesComerciales($body);
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
                    'INSERT INTO crm_cotizaciones (folio, empresa_id, contacto_id, oportunidad_id, ejecutivo_id, vendedor_id, estado, fecha_emision, fecha_validez, validez_oferta, moneda, condiciones_pago, plazo_entrega, lugar_entrega, subtotal, descuento, iva, total, notas, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, ?, ?)'
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
                    $comercial['validez_oferta'],
                    $comercial['moneda'],
                    $comercial['condiciones_pago'],
                    $comercial['plazo_entrega'],
                    $comercial['lugar_entrega'],
                    $notas,
                    crm_now(),
                ));
                $id = (int) $pdo->lastInsertId();
            }

            $del = $pdo->prepare('DELETE FROM crm_cotizacion_items WHERE cotizacion_id = ?');
            $del->execute(array($id));

            $subtotal = 0.0;
            $insItem = $pdo->prepare(
                'INSERT INTO crm_cotizacion_items (cotizacion_id, tipo_item, es_a_pedido, producto_id, marca_id, marca_nombre, codigo, descripcion, descripcion_detallada, imagen_url, cantidad, precio_unitario, costo_unitario, descuento_pct, subtotal, stock_al_cotizar)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($itemsIn as $raw) {
                if (!is_array($raw)) {
                    continue;
                }
                $item = self::normalizarItem($pdo, $raw);
                $subtotal += $item['subtotal'];
                $insItem->execute(array(
                    $id,
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

            if ($descuentoGlobal > $subtotal) {
                $descuentoGlobal = $subtotal;
            }
            $neto = $subtotal - $descuentoGlobal;
            $iva = round($neto * (crm_iva_pct() / 100), 2);
            $total = round($neto + $iva, 2);

            $upd = $pdo->prepare(
                'UPDATE crm_cotizaciones
                 SET empresa_id=?, contacto_id=?, oportunidad_id=?, ejecutivo_id=?, vendedor_id=?, estado=?, fecha_emision=?, fecha_validez=?, validez_oferta=?, moneda=?, condiciones_pago=?, plazo_entrega=?, lugar_entrega=?, subtotal=?, descuento=?, iva=?, total=?, notas=?, updated_at=?
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
                $comercial['validez_oferta'],
                $comercial['moneda'],
                $comercial['condiciones_pago'],
                $comercial['plazo_entrega'],
                $comercial['lugar_entrega'],
                $subtotal,
                $descuentoGlobal,
                $iva,
                $total,
                $notas,
                crm_now(),
                $id,
            ));

            Comisiones::sincronizarConCotizacion($pdo, $id, $vendedorId, $neto, $estado);
            Marcas::sincronizarCotizacion($pdo, $id, $body);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return self::show($id);
    }

    /**
     * @param int $id
     * @return array
     */
    public static function destroy($id)
    {
        $id = (int) $id;
        self::show($id);
        $pdo = crm_pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM crm_cotizacion_marcas WHERE cotizacion_id = ?')->execute(array($id));
            $pdo->prepare('DELETE FROM crm_comisiones WHERE cotizacion_id = ?')->execute(array($id));
            $pdo->prepare('DELETE FROM crm_cotizacion_items WHERE cotizacion_id = ?')->execute(array($id));
            $pdo->prepare('UPDATE crm_actividades SET cotizacion_id = NULL WHERE cotizacion_id = ?')->execute(array($id));
            $pdo->prepare('DELETE FROM crm_cotizaciones WHERE id = ?')->execute(array($id));
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return array('deleted' => true, 'id' => $id);
    }

    /**
     * Normaliza un ítem: producto de inventario o servicio libre (producto_id NULL).
     *
     * @param array $raw
     * @return array
     */
    public static function normalizarItem(PDO $pdo, array $raw)
    {
        $tipo = crm_lower(crm_str(isset($raw['tipo_item']) ? $raw['tipo_item'] : 'producto', 20));
        if ($tipo === '') {
            $tipo = 'producto';
        }
        if (!in_array($tipo, Catalog::itemTipos(), true)) {
            Http::fail('tipo_item inválido');
        }

        $productoId = crm_int(isset($raw['producto_id']) ? $raw['producto_id'] : 0, 0);
        $codigo = crm_str(isset($raw['codigo']) ? $raw['codigo'] : '', 50);
        $descripcion = crm_str(isset($raw['descripcion']) ? $raw['descripcion'] : '', 300);
        $detalle = crm_str(isset($raw['descripcion_detallada']) ? $raw['descripcion_detallada'] : '', 2000);
        $imagenUrl = isset($raw['imagen_url']) ? trim((string) $raw['imagen_url']) : '';
        $cantidad = crm_float(isset($raw['cantidad']) ? $raw['cantidad'] : 1, 1);
        $precio = crm_float(isset($raw['precio_unitario']) ? $raw['precio_unitario'] : 0, 0);
        $costo = crm_float(isset($raw['costo_unitario']) ? $raw['costo_unitario'] : 0, 0);
        $descPct = crm_float(isset($raw['descuento_pct']) ? $raw['descuento_pct'] : 0, 0);
        $stockSnap = null;
        $esAPedido = 0;
        $marcaId = 0;
        $marcaNombre = '';

        if ($tipo === 'a_pedido' || !empty($raw['es_a_pedido'])) {
            $tipo = 'a_pedido';
            $esAPedido = 1;
            $productoId = 0;
            if ($codigo === '') {
                $codigo = 'PEDIDO';
            }
            if ($descripcion === '') {
                Http::fail('El ítem a pedido requiere una descripción');
            }
            $marcaId = crm_int(isset($raw['marca_id']) ? $raw['marca_id'] : 0, 0);
            $marcaNombre = crm_str(isset($raw['marca_nombre']) ? $raw['marca_nombre'] : '', 150);
            if ($marcaId > 0) {
                $mStmt = $pdo->prepare('SELECT id, nombre FROM crm_marcas WHERE id = ? LIMIT 1');
                $mStmt->execute(array($marcaId));
                $marca = $mStmt->fetch(PDO::FETCH_ASSOC);
                if (!$marca) {
                    Http::fail('Marca no encontrada: id ' . $marcaId);
                }
                if ($marcaNombre === '') {
                    $marcaNombre = (string) $marca['nombre'];
                }
            }
            if ($costo < 0) {
                $costo = 0;
            }
        } elseif ($tipo === 'servicio') {
            $productoId = 0;
            $costo = 0;
            if ($codigo === '') {
                $codigo = 'SERV';
            }
            if ($descripcion === '') {
                Http::fail('El servicio requiere una descripción');
            }
        } elseif ($productoId > 0) {
            $colImg = ItemImagen::columnaInventario($pdo);
            $sqlProd = 'SELECT id, codigo, nombre, stock, precio_unitario';
            if ($colImg !== '') {
                $sqlProd .= ', ' . $colImg;
            }
            $sqlProd .= ' FROM productos WHERE id = ? LIMIT 1';
            $findProd = $pdo->prepare($sqlProd);
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
            $costo = 0;
            if ($imagenUrl === '') {
                $imagenUrl = ItemImagen::resolverInventario($prod);
            }
        }

        $imagenUrl = ItemImagen::normalizarEntrada($imagenUrl, null);

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

        return array(
            'tipo_item' => $tipo,
            'es_a_pedido' => $esAPedido,
            'producto_id' => $productoId > 0 ? $productoId : null,
            'marca_id' => $marcaId > 0 ? $marcaId : null,
            'marca_nombre' => $marcaNombre !== '' ? $marcaNombre : null,
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'descripcion_detallada' => $detalle !== '' ? $detalle : null,
            'imagen_url' => $imagenUrl !== '' ? $imagenUrl : null,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'costo_unitario' => $costo,
            'descuento_pct' => $descPct,
            'subtotal' => $line,
            'stock_al_cotizar' => $stockSnap,
        );
    }

    /**
     * @param array $body
     * @return array
     */
    public static function condicionesComerciales(array $body)
    {
        $moneda = strtoupper(crm_str(isset($body['moneda']) ? $body['moneda'] : 'CLP', 10));
        if ($moneda === '') {
            $moneda = 'CLP';
        }
        if (!in_array($moneda, Catalog::monedas(), true)) {
            Http::fail('Moneda inválida');
        }
        $validez = crm_str(isset($body['validez_oferta']) ? $body['validez_oferta'] : '', 100);
        $pago = crm_str(isset($body['condiciones_pago']) ? $body['condiciones_pago'] : '', 150);
        $plazo = crm_str(isset($body['plazo_entrega']) ? $body['plazo_entrega'] : '', 150);
        $lugar = crm_str(isset($body['lugar_entrega']) ? $body['lugar_entrega'] : '', 255);
        return array(
            'validez_oferta' => $validez !== '' ? $validez : null,
            'moneda' => $moneda,
            'condiciones_pago' => $pago !== '' ? $pago : null,
            'plazo_entrega' => $plazo !== '' ? $plazo : null,
            'lugar_entrega' => $lugar !== '' ? $lugar : null,
        );
    }
}
