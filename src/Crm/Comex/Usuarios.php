<?php

declare(strict_types=1);

namespace Crm\Comex;

use Crm\ApiException;
use Crm\Database\Connection;
use PDO;

require_once dirname(__DIR__, 2) . '/Schema.php';

/**
 * Perfiles de usuario COMEX: únicamente admin y comex.
 */
final class Usuarios
{
    public const ROL_ADMIN = 'admin';
    public const ROL_COMEX = 'comex';

    public const SEED_ADMIN_EMAIL = 'admin@comex.lpaezsis.cl';
    public const SEED_COMEX_EMAIL = 'comex@comex.lpaezsis.cl';
    public const SEED_PASSWORD = 'Comex2026!';

    /** @return list<string> */
    public static function roles(): array
    {
        return \Schema::roles();
    }

    public static function rolValido(string $rol): bool
    {
        return \Schema::rolValido(strtolower(trim($rol)));
    }

    public static function exigirRol(string $rol): string
    {
        $rol = strtolower(trim($rol));
        if (!self::rolValido($rol)) {
            throw new ApiException("rol debe ser 'admin' o 'comex'", 400);
        }
        return $rol;
    }

    public static function etiqueta(string $rol): string
    {
        return $rol === self::ROL_ADMIN ? 'Administrador' : 'COMEX';
    }

    public static function sembrar(?PDO $pdo = null): void
    {
        $pdo = $pdo ?? Connection::app();
        $now = function_exists('crm_now') ? crm_now() : date('Y-m-d H:i:s');
        $hash = password_hash(self::SEED_PASSWORD, PASSWORD_DEFAULT);
        $seeds = [
            ['Administrador', self::SEED_ADMIN_EMAIL, $hash, self::ROL_ADMIN, 1, $now],
            ['Operativo COMEX', self::SEED_COMEX_EMAIL, $hash, self::ROL_COMEX, 1, $now],
        ];
        foreach ($seeds as $row) {
            $chk = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
            $chk->execute([$row[1]]);
            if ($chk->fetch(PDO::FETCH_ASSOC)) {
                continue;
            }
            $ins = $pdo->prepare(
                'INSERT INTO usuarios (nombre, email, password_hash, rol, activo, creado_en)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $ins->execute($row);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listar(): array
    {
        $rows = Connection::app()->query(
            'SELECT id, nombre, email, rol, activo, creado_en FROM usuarios ORDER BY id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row)) {
                $out[] = self::publico($row);
            }
        }
        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function porId(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = Connection::app()->prepare(
            'SELECT id, nombre, email, rol, activo, creado_en FROM usuarios WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? self::publico($row) : null;
    }

    /** @return array<string, mixed>|null */
    public static function porEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '' || !str_contains($email, '@')) {
            return null;
        }
        $stmt = Connection::app()->prepare(
            'SELECT * FROM usuarios WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function autenticar(string $email, string $password): array
    {
        $row = self::porEmail($email);
        if ($row === null || !password_verify($password, (string) ($row['password_hash'] ?? ''))) {
            throw new ApiException('Credenciales inválidas', 401);
        }
        if ((int) ($row['activo'] ?? 0) !== 1) {
            throw new ApiException('Usuario inactivo', 403);
        }
        $pub = self::publico($row);
        self::guardarSesion($pub);
        return $pub;
    }

    public static function cerrarSesion(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['usuario_id'], $_SESSION['usuario_rol'], $_SESSION['usuario_nombre'], $_SESSION['usuario_email']);
        }
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function guardarSesion(array $user): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION['usuario_id'] = (int) ($user['id'] ?? 0);
        $_SESSION['usuario_rol'] = (string) ($user['rol'] ?? self::ROL_COMEX);
        $_SESSION['usuario_nombre'] = (string) ($user['nombre'] ?? '');
        $_SESSION['usuario_email'] = (string) ($user['email'] ?? '');
    }

    /** @return array<string, mixed>|null */
    public static function sesion(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        $id = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $rol = (string) ($_SESSION['usuario_rol'] ?? self::ROL_COMEX);
        if (!self::rolValido($rol)) {
            $rol = self::ROL_COMEX;
        }
        return [
            'id' => $id,
            'nombre' => (string) ($_SESSION['usuario_nombre'] ?? ''),
            'email' => (string) ($_SESSION['usuario_email'] ?? ''),
            'rol' => $rol,
            'rol_etiqueta' => self::etiqueta($rol),
            'activo' => true,
        ];
    }

    /**
     * Resuelve el actor del request.
     * En HTTP la sesión manda (sin sesión = operativo comex).
     * En CLI/tests se acepta rol/email en $opts.
     *
     * @param array<string, mixed> $opts
     * @return array<string, mixed>
     */
    public static function actorDesde(array $opts = []): array
    {
        if (PHP_SAPI !== 'cli') {
            $ses = self::sesion();
            return $ses ?? self::operativoPorDefecto();
        }
        $rolOpt = strtolower(trim((string) ($opts['rol'] ?? '')));
        if ($rolOpt !== '') {
            $rol = self::exigirRol($rolOpt);
            $email = strtolower(trim((string) ($opts['email'] ?? '')));
            if ($email === '') {
                $email = $rol === self::ROL_ADMIN ? self::SEED_ADMIN_EMAIL : self::SEED_COMEX_EMAIL;
            }
            $row = self::porEmail($email);
            if ($row !== null) {
                $pub = self::publico($row);
                $pub['rol'] = $rol;
                $pub['rol_etiqueta'] = self::etiqueta($rol);
                return $pub;
            }
            return [
                'id' => 0,
                'nombre' => self::etiqueta($rol),
                'email' => $email,
                'rol' => $rol,
                'rol_etiqueta' => self::etiqueta($rol),
                'activo' => true,
            ];
        }
        $email = strtolower(trim((string) ($opts['email'] ?? $opts['usuario'] ?? '')));
        if ($email !== '') {
            $row = self::porEmail($email);
            if ($row !== null) {
                if ((int) ($row['activo'] ?? 0) !== 1) {
                    throw new ApiException('Usuario inactivo', 403);
                }
                return self::publico($row);
            }
        }
        return self::sesion() ?? self::operativoPorDefecto();
    }

    /** @return array<string, mixed> */
    public static function operativoPorDefecto(): array
    {
        return [
            'id' => 0,
            'nombre' => 'Operativo COMEX',
            'email' => self::SEED_COMEX_EMAIL,
            'rol' => self::ROL_COMEX,
            'rol_etiqueta' => self::etiqueta(self::ROL_COMEX),
            'activo' => true,
        ];
    }

    public static function esAdmin(array $actor): bool
    {
        return ($actor['rol'] ?? '') === self::ROL_ADMIN;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function publico(array $row): array
    {
        $rol = self::exigirRol((string) ($row['rol'] ?? self::ROL_COMEX));
        return [
            'id' => (int) ($row['id'] ?? 0),
            'nombre' => (string) ($row['nombre'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'rol' => $rol,
            'rol_etiqueta' => self::etiqueta($rol),
            'activo' => (int) ($row['activo'] ?? 0) === 1,
            'creado_en' => (string) ($row['creado_en'] ?? ''),
        ];
    }
}
