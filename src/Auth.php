<?php

declare(strict_types=1);

/**
 * Sesión COMEX: login obligatorio del panel.
 * Clave de sesión: $_SESSION['user_id'].
 */
final class Auth
{
    public static function isLoggedIn(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $id = (int) ($_SESSION['user_id'] ?? 0);
        if ($id <= 0) {
            $legacy = (int) ($_SESSION['usuario_id'] ?? 0);
            if ($legacy > 0) {
                $_SESSION['user_id'] = $legacy;
                $id = $legacy;
            }
        }
        return $id > 0;
    }

    /**
     * Vistas HTML: redirige a login.php si no hay sesión.
     * En CLI no interrumpe la suite (PHP_SAPI === 'cli').
     */
    public static function requireLogin(): void
    {
        if (self::isLoggedIn()) {
            return;
        }
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (!headers_sent()) {
            header('Location: login.php');
        }
        http_response_code(302);
        exit;
    }

    /**
     * API JSON: 401 si no hay sesión (salvo rutas públicas).
     */
    public static function requireApi(): void
    {
        if (self::isLoggedIn()) {
            return;
        }
        if (PHP_SAPI === 'cli') {
            return;
        }
        \Crm\Http::json(\Crm\Http::payloadFail('No autenticado'), 401);
    }

    public static function userId(): int
    {
        return self::isLoggedIn() ? (int) ($_SESSION['user_id'] ?? 0) : 0;
    }
}
