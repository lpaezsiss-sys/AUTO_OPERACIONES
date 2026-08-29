<?php

declare(strict_types=1);

namespace Crm;

use PDO;
use PDOException;

/**
 * Catálogo CRM (`productos`).
 * Stock de filas existentes: solo lectura + overlay de inventario (nunca UPDATE stock).
 * SKU que solo existe en inventario: INSERT con stock 0 para obtener id de cotizador.
 */
final class Productos
{
    /**
     * @return array
     */
    public static function index()
    {
        $q = crm_str(isset($_GET['q']) ? $_GET['q'] : '', 120);
        $bajo = isset($_GET['bajo_stock']) && (string) $_GET['bajo_stock'] === '1';
        $colImg = ItemImagen::columnaInventario(crm_pdo());
        $sql = 'SELECT id, codigo, nombre, descripcion, stock, precio_unitario, umbral_stock, unidad, activo, updated_at';
        if ($colImg !== '') {
            $sql .= ', ' . $colImg;
        }
        $sql .= ' FROM productos
                WHERE activo = 1';
        $params = array();
        if ($q !== '') {
            $sql .= ' AND (codigo LIKE ? ESCAPE \'\\\' OR nombre LIKE ? ESCAPE \'\\\' OR descripcion LIKE ? ESCAPE \'\\\')';
            $like = InventarioStock::likeNeedle($q);
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($bajo) {
            $sql .= ' AND stock <= umbral_stock';
        }
        $sql .= ' ORDER BY nombre ASC';
        $stmt = crm_pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            $rows = array();
        }
        if ($q !== '') {
            $rows = self::completarDesdeInventario($q, $rows, 50);
        }
        $out = array();
        foreach ($rows as $row) {
            $row['precio_unitario'] = (float) $row['precio_unitario'];
            $row['umbral_stock'] = (float) $row['umbral_stock'];
            $row = InventarioStock::aplicarAFila($row);
            $row['bajo_stock'] = $row['stock'] <= $row['umbral_stock'];
            if ($bajo && !$row['bajo_stock']) {
                continue;
            }
            $out[] = ItemImagen::anexarAProducto($row);
        }
        return array('productos' => $out);
    }

    /**
     * Búsqueda del cotizador: catálogo CRM + SKUs de inventario (alta stock 0 si faltan).
     *
     * @param string $q
     * @param int $limit
     * @return list<array>
     */
    public static function buscarParaCotizador($q, $limit = 20)
    {
        $q = trim((string) $q);
        $limit = (int) $limit;
        if ($limit < 1) {
            $limit = 20;
        }
        if ($limit > 50) {
            $limit = 50;
        }
        if ($q === '') {
            return array();
        }
        $colImg = ItemImagen::columnaInventario(crm_pdo());
        $sql = 'SELECT id, codigo, codigo AS sku, nombre, descripcion, stock, precio_unitario, unidad';
        if ($colImg !== '') {
            $sql .= ', ' . $colImg;
        }
        $sql .= ' FROM productos
                WHERE activo = 1
                  AND (nombre LIKE ? ESCAPE \'\\\' OR codigo LIKE ? ESCAPE \'\\\')
                ORDER BY nombre ASC
                LIMIT ' . $limit;
        $like = InventarioStock::likeNeedle($q);
        $stmt = crm_pdo()->prepare($sql);
        $stmt->execute(array($like, $like));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            $rows = array();
        }
        $rows = self::completarDesdeInventario($q, $rows, $limit);
        $out = array();
        foreach ($rows as $row) {
            $row['precio_unitario'] = (float) $row['precio_unitario'];
            $row['sku'] = (string) $row['codigo'];
            $row = InventarioStock::aplicarAFila($row);
            $out[] = ItemImagen::anexarAProducto($row);
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * Incorpora SKUs hallados en inventario que aún no están en el catálogo CRM.
     * INSERT solo si el código no existe. Nunca UPDATE productos.stock.
     *
     * @param string $q
     * @param list<array> $rows
     * @param int $limit
     * @return list<array>
     */
    public static function completarDesdeInventario($q, array $rows, $limit = 20)
    {
        $limit = (int) $limit;
        $hits = InventarioStock::buscar($q, $limit);
        if ($hits === array()) {
            return $rows;
        }
        $have = array();
        foreach ($rows as $row) {
            $cod = '';
            if (isset($row['codigo'])) {
                $cod = (string) $row['codigo'];
            } elseif (isset($row['sku'])) {
                $cod = (string) $row['sku'];
            }
            if ($cod !== '') {
                $have[strtoupper($cod)] = true;
            }
        }
        foreach ($hits as $inv) {
            $code = (string) $inv['code'];
            $key = strtoupper($code);
            if (isset($have[$key])) {
                continue;
            }
            $fila = self::asegurarDesdeInventario($inv);
            if (!is_array($fila)) {
                continue;
            }
            $rows[] = $fila;
            $have[$key] = true;
            if (count($rows) >= $limit) {
                break;
            }
        }
        return $rows;
    }

    /**
     * Alta silenciosa de SKU de inventario: stock 0, no toca filas existentes.
     *
     * @param array $inv
     * @return array|null
     */
    public static function asegurarDesdeInventario(array $inv)
    {
        $codigo = trim((string) (isset($inv['code']) ? $inv['code'] : ''));
        if ($codigo === '') {
            return null;
        }
        $existente = self::fetchCatalogoPorCodigo($codigo);
        if (is_array($existente)) {
            return $existente;
        }
        $nombre = crm_str(isset($inv['name']) ? $inv['name'] : $codigo, 300);
        if ($nombre === '') {
            $nombre = $codigo;
        }
        $descripcion = crm_str(isset($inv['description']) ? $inv['description'] : $nombre, 500);
        if ($descripcion === '') {
            $descripcion = $nombre;
        }
        $precio = 0.0;
        if (isset($inv['averageUnitCost']) && $inv['averageUnitCost'] !== null && $inv['averageUnitCost'] !== '') {
            $precio = crm_float($inv['averageUnitCost'], 0);
            if ($precio < 0) {
                $precio = 0;
            }
        }
        $pdo = crm_pdo();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO productos (codigo, nombre, descripcion, stock, precio_unitario, umbral_stock, unidad, activo)
                 VALUES (?, ?, ?, 0, ?, 1, ?, 1)'
            );
            $ins->execute(array($codigo, $nombre, $descripcion, $precio, 'UN'));
        } catch (PDOException $e) {
            $existente = self::fetchCatalogoPorCodigo($codigo);
            if (is_array($existente)) {
                return $existente;
            }
            throw $e;
        }
        return self::fetchCatalogoPorCodigo($codigo);
    }

    /**
     * @param string $codigo
     * @return array|null
     */
    public static function fetchCatalogoPorCodigo($codigo)
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return null;
        }
        $colImg = ItemImagen::columnaInventario(crm_pdo());
        $sql = 'SELECT id, codigo, codigo AS sku, nombre, descripcion, stock, precio_unitario, umbral_stock, unidad, activo';
        if (self::tablaTieneUpdatedAt()) {
            $sql .= ', updated_at';
        }
        if ($colImg !== '') {
            $sql .= ', ' . $colImg;
        }
        $sql .= ' FROM productos WHERE codigo = ? LIMIT 1';
        $stmt = crm_pdo()->prepare($sql);
        $stmt->execute(array($codigo));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return $row;
        }
        $sqlCi = str_replace('WHERE codigo = ?', 'WHERE LOWER(codigo) = LOWER(?)', $sql);
        $stmt = crm_pdo()->prepare($sqlCi);
        $stmt->execute(array($codigo));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return bool
     */
    private static function tablaTieneUpdatedAt()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = false;
        try {
            $stmt = crm_pdo()->query('SELECT updated_at FROM productos LIMIT 1');
            if ($stmt) {
                $cache = true;
            }
        } catch (PDOException $e) {
            $cache = false;
        }
        return $cache;
    }

    /**
     * @param int $id
     * @return array
     */
    public static function find($id)
    {
        $colImg = ItemImagen::columnaInventario(crm_pdo());
        $sql = 'SELECT id, codigo, nombre, descripcion, stock, precio_unitario, umbral_stock, unidad, activo';
        if ($colImg !== '') {
            $sql .= ', ' . $colImg;
        }
        $sql .= ' FROM productos WHERE id = ? LIMIT 1';
        $stmt = crm_pdo()->prepare($sql);
        $stmt->execute(array((int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            Http::fail('Producto no encontrado en inventario', 404);
        }
        $row = InventarioStock::aplicarAFila($row);
        return ItemImagen::anexarAProducto($row);
    }

    /**
     * Alta de SKU nuevo (stock 0). Nunca actualiza filas existentes ni el stock.
     *
     * @param array $body
     * @return array
     */
    public static function altaCatalogo(array $body)
    {
        $nombre = crm_str(isset($body['nombre']) ? $body['nombre'] : (isset($body['descripcion']) ? $body['descripcion'] : ''), 300);
        if ($nombre === '') {
            Http::fail('El nombre del producto es obligatorio');
        }
        $codigo = strtoupper(crm_str(isset($body['codigo']) ? $body['codigo'] : '', 50));
        if ($codigo === '') {
            $codigo = self::siguienteCodigoAPedido();
        }
        $pdo = crm_pdo();
        $dup = $pdo->prepare('SELECT id FROM productos WHERE codigo = ? LIMIT 1');
        $dup->execute(array($codigo));
        if ($dup->fetchColumn()) {
            Http::fail('Ya existe un producto con el código ' . $codigo);
        }
        $precio = crm_float(isset($body['precio_unitario']) ? $body['precio_unitario'] : 0, 0);
        if ($precio < 0) {
            $precio = 0;
        }
        $unidad = crm_str(isset($body['unidad']) ? $body['unidad'] : 'UN', 20);
        if ($unidad === '') {
            $unidad = 'UN';
        }
        $marcaNombre = crm_str(isset($body['marca_nombre']) ? $body['marca_nombre'] : '', 150);
        $descripcion = crm_str(isset($body['descripcion']) ? $body['descripcion'] : $nombre, 500);
        if ($marcaNombre !== '' && strpos($descripcion, $marcaNombre) === false) {
            $descripcion = $marcaNombre . ' · ' . $descripcion;
        }
        $ins = $pdo->prepare(
            'INSERT INTO productos (codigo, nombre, descripcion, stock, precio_unitario, umbral_stock, unidad, activo)
             VALUES (?, ?, ?, 0, ?, 1, ?, 1)'
        );
        $ins->execute(array($codigo, $nombre, $descripcion, $precio, $unidad));
        $id = (int) $pdo->lastInsertId();
        return array('producto' => self::find($id));
    }

    /**
     * @param string $nombre
     * @return bool
     */
    public static function existePorNombre($nombre)
    {
        $nombre = trim((string) $nombre);
        if ($nombre === '') {
            return false;
        }
        $stmt = crm_pdo()->prepare('SELECT id FROM productos WHERE LOWER(nombre) = LOWER(?) LIMIT 1');
        $stmt->execute(array($nombre));
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return string
     */
    private static function siguienteCodigoAPedido()
    {
        $year = date('Y');
        $like = 'APD-' . $year . '-%';
        $stmt = crm_pdo()->prepare(
            'SELECT codigo FROM productos WHERE codigo LIKE ? ORDER BY codigo DESC LIMIT 1'
        );
        $stmt->execute(array($like));
        $last = $stmt->fetchColumn();
        $n = 1;
        if (is_string($last) && preg_match('/-(\d+)$/', $last, $m)) {
            $n = ((int) $m[1]) + 1;
        }
        return 'APD-' . $year . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
