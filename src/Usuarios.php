<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Usuarios
{
    /**
     * @return array
     */
    public static function roles()
    {
        return array('admin', 'vendedor');
    }

    /**
     * @return array
     */
    public static function index()
    {
        $rows = crm_pdo()->query(
            'SELECT id, nombre, email, rol, activo, created_at
             FROM crm_usuarios
             ORDER BY activo DESC, nombre ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return array('usuarios' => is_array($rows) ? $rows : array());
    }

    /**
     * @param int $id
     * @return array|null
     */
    public static function obtener($id)
    {
        $stmt = crm_pdo()->prepare(
            'SELECT id, nombre, email, rol, activo, created_at FROM crm_usuarios WHERE id = ? LIMIT 1'
        );
        $stmt->execute(array((int) $id));
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
        $id = (int) $id;
        $nombre = crm_str(isset($data['nombre']) ? $data['nombre'] : '', 160);
        $email = crm_lower(crm_str(isset($data['email']) ? $data['email'] : '', 190));
        $rol = crm_str(isset($data['rol']) ? $data['rol'] : 'vendedor', 20);
        $password = isset($data['password']) ? trim((string) $data['password']) : '';

        if ($nombre === '' || $email === '') {
            Http::fail('Nombre completo y correo son obligatorios.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Http::fail('Correo electrónico inválido.');
        }
        if (!in_array($rol, self::roles(), true)) {
            Http::fail('Rol inválido. Use admin o vendedor.');
        }

        $activo = 1;
        if (array_key_exists('activo', $data)) {
            $activo = !empty($data['activo']) ? 1 : 0;
        }

        $pdo = crm_pdo();
        $actual = null;
        if ($id > 0) {
            $actual = self::obtener($id);
            if ($actual === null) {
                Http::fail('Usuario no encontrado', 404);
            }
        } else {
            if ($password === '') {
                Http::fail('La contraseña es obligatoria al crear un usuario.');
            }
        }

        if ($password !== '') {
            self::validarPassword($password);
        }

        $dup = $pdo->prepare('SELECT id FROM crm_usuarios WHERE email = ? AND id <> ? LIMIT 1');
        $dup->execute(array($email, $id));
        if ($dup->fetchColumn()) {
            Http::fail('Ya existe un usuario con ese correo.');
        }

        self::protegerUltimoAdmin($id, $actual, $rol, $activo);

        if ($id > 0) {
            if ($password !== '') {
                $pdo->prepare(
                    'UPDATE crm_usuarios SET nombre = ?, email = ?, rol = ?, activo = ?, password_hash = ? WHERE id = ?'
                )->execute(array($nombre, $email, $rol, $activo, password_hash($password, PASSWORD_DEFAULT), $id));
            } else {
                $pdo->prepare(
                    'UPDATE crm_usuarios SET nombre = ?, email = ?, rol = ?, activo = ? WHERE id = ?'
                )->execute(array($nombre, $email, $rol, $activo, $id));
            }
            return array('usuario' => self::obtener($id));
        }

        $pdo->prepare(
            'INSERT INTO crm_usuarios (nombre, email, password_hash, rol, activo) VALUES (?, ?, ?, ?, ?)'
        )->execute(array($nombre, $email, password_hash($password, PASSWORD_DEFAULT), $rol, $activo));
        $newId = (int) $pdo->lastInsertId();
        return array('usuario' => self::obtener($newId));
    }

    /**
     * @param int $id
     * @param string $password
     * @return array
     */
    public static function cambiarPassword($id, $password)
    {
        $id = (int) $id;
        $password = trim((string) $password);
        if ($id <= 0) {
            Http::fail('Debe indicar el usuario.');
        }
        if (self::obtener($id) === null) {
            Http::fail('Usuario no encontrado', 404);
        }
        self::validarPassword($password);
        crm_pdo()->prepare('UPDATE crm_usuarios SET password_hash = ? WHERE id = ?')
            ->execute(array(password_hash($password, PASSWORD_DEFAULT), $id));
        return array('usuario' => self::obtener($id));
    }

    /**
     * @param string $password
     * @return void
     */
    private static function validarPassword($password)
    {
        if (strlen((string) $password) < 8) {
            Http::fail('La contraseña debe tener al menos 8 caracteres.');
        }
    }

    /**
     * @param int $id
     * @param array|null $actual
     * @param string $rol
     * @param int $activo
     * @return void
     */
    private static function protegerUltimoAdmin($id, $actual, $rol, $activo)
    {
        if ($id <= 0 || !is_array($actual)) {
            return;
        }
        $eraAdminActivo = (string) $actual['rol'] === 'admin' && (int) $actual['activo'] === 1;
        if (!$eraAdminActivo) {
            return;
        }
        $sigueAdminActivo = $rol === 'admin' && (int) $activo === 1;
        if ($sigueAdminActivo) {
            return;
        }
        $stmt = crm_pdo()->prepare(
            "SELECT COUNT(*) FROM crm_usuarios WHERE rol = 'admin' AND activo = 1 AND id <> ?"
        );
        $stmt->execute(array((int) $id));
        if ((int) $stmt->fetchColumn() < 1) {
            Http::fail('No se puede dejar el sistema sin un administrador activo.');
        }
    }
}
