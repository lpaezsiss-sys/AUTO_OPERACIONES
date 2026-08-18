<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class ListasPrecios
{
    /**
     * @param PDO|null $pdo
     * @return void
     */
    public static function sembrarSiVacio($pdo = null)
    {
        $pdo = $pdo instanceof PDO ? $pdo : crm_pdo();
        try {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM crm_listas_precios')->fetchColumn();
        } catch (\PDOException $e) {
            return;
        }
        if ($count > 0) {
            return;
        }
        $pdo->prepare(
            'INSERT INTO crm_listas_precios (nombre, porcentaje_ajuste, es_default, estado, updated_at)
             VALUES (?, 0, 1, ?, ?)'
        )->execute(array('Lista general', 'activa', crm_now()));
    }

    /**
     * @return array
     */
    public static function index()
    {
        self::sembrarSiVacio();
        $rows = crm_pdo()->query(
            'SELECT * FROM crm_listas_precios ORDER BY es_default DESC, estado ASC, nombre ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return array('listas' => is_array($rows) ? $rows : array());
    }

    /**
     * @param int $id
     * @return array|null
     */
    public static function obtener($id)
    {
        $stmt = crm_pdo()->prepare('SELECT * FROM crm_listas_precios WHERE id = ? LIMIT 1');
        $stmt->execute(array((int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    /**
     * @return array|null
     */
    public static function predeterminada()
    {
        $stmt = crm_pdo()->query(
            "SELECT * FROM crm_listas_precios WHERE es_default = 1 AND estado = 'activa' ORDER BY id ASC LIMIT 1"
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if ($row) {
            return $row;
        }
        $stmt = crm_pdo()->query(
            "SELECT * FROM crm_listas_precios WHERE estado = 'activa' ORDER BY id ASC LIMIT 1"
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return $row ? $row : null;
    }

    /**
     * @param array $data
     * @param int $id
     * @return array
     */
    public static function guardar(array $data, $id = 0)
    {
        $id = (int) $id;
        $nombre = crm_str(isset($data['nombre']) ? $data['nombre'] : '', 160);
        if ($nombre === '') {
            Http::fail('El nombre de la lista es obligatorio.');
        }
        $pct = crm_float(isset($data['porcentaje_ajuste']) ? $data['porcentaje_ajuste'] : 0, 0);
        if ($pct < -99.99 || $pct > 999.99) {
            Http::fail('El porcentaje de ajuste debe estar entre -99.99 y 999.99.');
        }
        $estado = crm_str(isset($data['estado']) ? $data['estado'] : 'activa', 20);
        if (!in_array($estado, array('activa', 'inactiva'), true)) {
            Http::fail('Estado de lista inválido.');
        }
        $esDefault = 0;
        if (array_key_exists('es_default', $data)) {
            $esDefault = !empty($data['es_default']) ? 1 : 0;
        }
        if ($esDefault === 1 && $estado !== 'activa') {
            Http::fail('La lista predeterminada debe estar activa.');
        }

        $pdo = crm_pdo();
        if ($id > 0 && self::obtener($id) === null) {
            Http::fail('Lista de precios no encontrada', 404);
        }

        $pdo->beginTransaction();
        try {
            if ($esDefault === 1) {
                $pdo->exec('UPDATE crm_listas_precios SET es_default = 0');
            }
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE crm_listas_precios
                     SET nombre = ?, porcentaje_ajuste = ?, es_default = ?, estado = ?, updated_at = ?
                     WHERE id = ?'
                )->execute(array($nombre, round($pct, 2), $esDefault, $estado, crm_now(), $id));
            } else {
                $pdo->prepare(
                    'INSERT INTO crm_listas_precios (nombre, porcentaje_ajuste, es_default, estado, updated_at)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute(array($nombre, round($pct, 2), $esDefault, $estado, crm_now()));
                $id = (int) $pdo->lastInsertId();
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return array('lista' => self::obtener($id));
    }

    /**
     * @param int $id
     * @return array
     */
    public static function eliminar($id)
    {
        $id = (int) $id;
        $row = self::obtener($id);
        if ($row === null) {
            Http::fail('Lista de precios no encontrada', 404);
        }
        $pdo = crm_pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE crm_empresas SET lista_precio_id = NULL WHERE lista_precio_id = ?')->execute(array($id));
            $pdo->prepare('UPDATE crm_cotizaciones SET lista_precio_id = NULL WHERE lista_precio_id = ?')->execute(array($id));
            $pdo->prepare('DELETE FROM crm_listas_precios WHERE id = ?')->execute(array($id));
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return array('deleted' => true, 'id' => $id);
    }
}
