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
        $sql = 'SELECT id, codigo, nombre, descripcion, stock, precio_unitario, umbral_stock, unidad, activo, updated_at
                FROM productos
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
        $stmt = crm_pdo()->prepare(
            'SELECT id, codigo, nombre, descripcion, stock, precio_unitario, umbral_stock, unidad, activo
             FROM productos WHERE id = ? LIMIT 1'
        );
        $stmt->execute(array((int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            Http::fail('Producto no encontrado en inventario', 404);
        }
        return $row;
    }
}
