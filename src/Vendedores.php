<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Vendedores
{
    /**
     * @return array
     */
    public static function index()
    {
        $pdo = crm_pdo();
        $sql = 'SELECT v.*, u.email AS usuario_email, u.nombre AS usuario_nombre
                FROM crm_vendedores v
                LEFT JOIN crm_usuarios u ON u.id = v.usuario_id
                ORDER BY v.activo DESC, v.nombre_completo ASC';
        $vendedores = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $usuarios = $pdo->query(
            'SELECT id, nombre, email, rol FROM crm_usuarios WHERE activo = 1 ORDER BY nombre ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return array(
            'vendedores' => is_array($vendedores) ? $vendedores : array(),
            'usuarios' => is_array($usuarios) ? $usuarios : array(),
        );
    }

    /**
     * @param int $id
     * @return array|null
     */
    public static function obtener(PDO $pdo, $id)
    {
        $stmt = $pdo->prepare('SELECT * FROM crm_vendedores WHERE id = ? LIMIT 1');
        $stmt->execute(array((int) $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    /**
     * @param int $usuarioId
     * @return array|null
     */
    public static function porUsuario(PDO $pdo, $usuarioId)
    {
        $stmt = $pdo->prepare('SELECT * FROM crm_vendedores WHERE usuario_id = ? LIMIT 1');
        $stmt->execute(array((int) $usuarioId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : null;
    }

    /**
     * @param array $data
     * @param int $id
     * @return array
     */
    public static function guardar(array $data, $id = 0)
    {
        $pdo = crm_pdo();
        $nombre = crm_str(isset($data['nombre_completo']) ? $data['nombre_completo'] : '', 150);
        $email = crm_lower(crm_str(isset($data['email']) ? $data['email'] : '', 150));
        if ($nombre === '' || $email === '') {
            Http::fail('Nombre completo y email son obligatorios.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Http::fail('Email de vendedor inválido.');
        }

        $usuarioId = crm_int(isset($data['usuario_id']) ? $data['usuario_id'] : 0, 0);
        if ($usuarioId <= 0) {
            $usuarioId = null;
        }

        $pct = crm_float(isset($data['comision_porcentaje']) ? $data['comision_porcentaje'] : 0, 0);
        if ($pct < 0 || $pct > 100) {
            Http::fail('La comisión debe estar entre 0 y 100.');
        }

        $activo = 1;
        if (array_key_exists('activo', $data)) {
            $activo = !empty($data['activo']) ? 1 : 0;
        }

        $params = array(
            $usuarioId,
            $nombre,
            $email,
            crm_str(isset($data['telefono']) ? $data['telefono'] : '', 50),
            round($pct, 2),
            $activo,
        );

        if ((int) $id > 0) {
            if (!self::obtener($pdo, $id)) {
                Http::fail('Vendedor no encontrado', 404);
            }
            $sql = 'UPDATE crm_vendedores SET
                        usuario_id = ?,
                        nombre_completo = ?,
                        email = ?,
                        telefono = ?,
                        comision_porcentaje = ?,
                        activo = ?
                    WHERE id = ?';
            $params[] = (int) $id;
            $pdo->prepare($sql)->execute($params);
            return array('vendedor' => self::obtener($pdo, $id));
        }

        $sql = 'INSERT INTO crm_vendedores
                    (usuario_id, nombre_completo, email, telefono, comision_porcentaje, activo)
                VALUES (?, ?, ?, ?, ?, ?)';
        $pdo->prepare($sql)->execute($params);
        $newId = (int) $pdo->lastInsertId();
        return array('vendedor' => self::obtener($pdo, $newId));
    }
}
