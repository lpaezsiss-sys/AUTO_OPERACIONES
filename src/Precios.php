<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Precios
{
    /**
     * Último precio cotizado del SKU a esa empresa. LIMIT 1 sobre índices compuestos.
     *
     * @param int $empresaId
     * @param int $productoId
     * @return array|null
     */
    public static function ultimoHistorial($empresaId, $productoId)
    {
        $empresaId = (int) $empresaId;
        $productoId = (int) $productoId;
        if ($empresaId <= 0 || $productoId <= 0) {
            return null;
        }
        $sql = 'SELECT i.precio_unitario, c.fecha_emision, c.folio, c.id AS cotizacion_id, c.estado
                FROM crm_cotizacion_items i
                INNER JOIN crm_cotizaciones c ON c.id = i.cotizacion_id
                WHERE c.empresa_id = ?
                  AND i.producto_id = ?
                  AND i.tipo_item = ?
                  AND c.estado NOT IN (?, ?)
                ORDER BY c.id DESC
                LIMIT 1';
        $stmt = crm_pdo()->prepare($sql);
        $stmt->execute(array($empresaId, $productoId, 'producto', 'rechazada', 'vencida'));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    /**
     * Jerarquía: historial cliente → lista de precios → precio base inventario.
     *
     * @param int $empresaId
     * @param int $productoId
     * @param int $listaPrecioId
     * @return array
     */
    public static function resolver($empresaId, $productoId, $listaPrecioId = 0)
    {
        $empresaId = (int) $empresaId;
        $productoId = (int) $productoId;
        $listaPrecioId = (int) $listaPrecioId;
        if ($productoId <= 0) {
            Http::fail('Debe indicar el producto');
        }

        $prodStmt = crm_pdo()->prepare(
            'SELECT id, codigo, nombre, precio_unitario, stock FROM productos WHERE id = ? LIMIT 1'
        );
        $prodStmt->execute(array($productoId));
        $prod = $prodStmt->fetch(PDO::FETCH_ASSOC);
        if (!$prod) {
            Http::fail('Producto de inventario no encontrado', 404);
        }
        $base = (float) $prod['precio_unitario'];

        $hist = self::ultimoHistorial($empresaId, $productoId);
        if (is_array($hist)) {
            $precio = (float) $hist['precio_unitario'];
            $fechaFmt = self::fechaDmy((string) $hist['fecha_emision']);
            $badge = 'Último precio cliente: ' . self::clp($precio) . ' el ' . $fechaFmt;
            return self::pack($prod, $base, $precio, 'historial', null, $hist, $badge);
        }

        $lista = self::resolverLista($empresaId, $listaPrecioId);
        if (is_array($lista)) {
            $pct = (float) $lista['porcentaje_ajuste'];
            $precio = self::aplicarAjuste($base, $pct);
            $badge = 'Lista ' . (string) $lista['nombre'] . ': ' . ($pct >= 0 ? '+' : '') . rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',') . '%';
            return self::pack($prod, $base, $precio, 'lista', $lista, null, $badge);
        }

        return self::pack($prod, $base, $base, 'base', null, null, '');
    }

    /**
     * @param float $base
     * @param float $pct
     * @return float
     */
    public static function aplicarAjuste($base, $pct)
    {
        return round((float) $base * (1 + ((float) $pct / 100)), 2);
    }

    /**
     * @param int $empresaId
     * @param int $listaPrecioId
     * @return array|null
     */
    public static function resolverLista($empresaId, $listaPrecioId = 0)
    {
        $listaPrecioId = (int) $listaPrecioId;
        if ($listaPrecioId <= 0 && $empresaId > 0) {
            $emp = crm_pdo()->prepare('SELECT lista_precio_id FROM crm_empresas WHERE id = ? LIMIT 1');
            $emp->execute(array((int) $empresaId));
            $listaPrecioId = (int) $emp->fetchColumn();
        }
        if ($listaPrecioId > 0) {
            $lista = ListasPrecios::obtener($listaPrecioId);
            if (is_array($lista) && (string) $lista['estado'] === 'activa') {
                return $lista;
            }
        }
        return ListasPrecios::predeterminada();
    }

    /**
     * @param string $fecha
     * @return string
     */
    public static function fechaDmy($fecha)
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $fecha, $m)) {
            return $m[3] . '/' . $m[2] . '/' . $m[1];
        }
        return (string) $fecha;
    }

    /**
     * @param float $n
     * @return string
     */
    public static function clp($n)
    {
        return '$' . number_format((float) $n, 0, ',', '.');
    }

    /**
     * @param array $prod
     * @param float $base
     * @param float $precio
     * @param string $origen
     * @param array|null $lista
     * @param array|null $hist
     * @param string $badge
     * @return array
     */
    private static function pack(array $prod, $base, $precio, $origen, $lista, $hist, $badge)
    {
        $stock = InventarioStock::stockPorCodigo((string) $prod['codigo']);
        if ($stock === null) {
            $stock = (float) $prod['stock'];
        }

        return array(
            'producto_id' => (int) $prod['id'],
            'codigo' => (string) $prod['codigo'],
            'stock' => $stock,
            'precio_base' => (float) $base,
            'precio_unitario' => (float) $precio,
            'origen' => (string) $origen,
            'lista_precio_id' => is_array($lista) ? (int) $lista['id'] : null,
            'porcentaje_ajuste' => is_array($lista) ? (float) $lista['porcentaje_ajuste'] : null,
            'historial' => is_array($hist) ? array(
                'precio_unitario' => (float) $hist['precio_unitario'],
                'fecha_emision' => (string) $hist['fecha_emision'],
                'folio' => (string) $hist['folio'],
                'cotizacion_id' => (int) $hist['cotizacion_id'],
            ) : null,
            'badge' => (string) $badge,
        );
    }
}
