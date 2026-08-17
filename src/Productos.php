<?php

declare(strict_types=1);

namespace Crm;

use PDO;

/**
 * Lectura de stock/precio desde la tabla de inventario `productos`.
 * No duplica lógica de inventario: solo SELECT.
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
            $sql .= ' AND (codigo LIKE ? OR nombre LIKE ? OR descripcion LIKE ?)';
            $like = '%' . $q . '%';
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
        foreach ($rows as &$row) {
            $row['stock'] = (float) $row['stock'];
            $row['precio_unitario'] = (float) $row['precio_unitario'];
            $row['umbral_stock'] = (float) $row['umbral_stock'];
            $row['bajo_stock'] = $row['stock'] <= $row['umbral_stock'];
            $row = ItemImagen::anexarAProducto($row);
        }
        unset($row);
        return array('productos' => $rows);
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
