<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Auth
{
    /**
     * @return array|null
     */
    public static function user()
    {
        $id = (int) (isset($_SESSION['crm_user_id']) ? $_SESSION['crm_user_id'] : 0);
        if ($id <= 0) {
            return null;
        }
        $stmt = crm_pdo()->prepare(
            'SELECT id, nombre, email, rol, activo FROM crm_usuarios WHERE id = ? LIMIT 1'
        );
        $stmt->execute(array($id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int) $row['activo'] !== 1) {
            self::logout();
            return null;
        }
        return $row;
    }

    /**
     * @return array
     */
    public static function requireUser()
    {
        $user = self::user();
        if ($user === null) {
            Http::fail('No autenticado', 401);
        }
        return $user;
    }

    /**
     * @return array
     */
    public static function requireAdmin()
    {
        $user = self::requireUser();
        if ((string) $user['rol'] !== 'admin') {
            Http::fail('Requiere rol administrador', 403);
        }
        return $user;
    }

    /**
     * @param string $email
     * @param string $password
     * @return array
     */
    public static function login($email, $password)
    {
        $email = crm_lower(trim((string) $email));
        $password = (string) $password;
        if ($email === '' || $password === '') {
            Http::fail('Email y contraseña son obligatorios');
        }
        $stmt = crm_pdo()->prepare(
            'SELECT id, nombre, email, password_hash, rol, activo FROM crm_usuarios WHERE email = ? LIMIT 1'
        );
        $stmt->execute(array($email));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int) $row['activo'] !== 1 || !password_verify($password, (string) $row['password_hash'])) {
            Http::fail('Credenciales inválidas', 401);
        }
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['crm_user_id'] = (int) $row['id'];
        $_SESSION['crm_login_at'] = crm_now();
        unset($row['password_hash']);
        return $row;
    }

    public static function logout()
    {
        $_SESSION = array();
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
    }
}
