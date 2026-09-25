<?php

declare(strict_types=1);

/**
 * Migración de esquema COMEX — tabla `usuarios` (SQLite comex.db / MySQL).
 * Roles válidos: admin | comex. El resto del catálogo vive en Crm\Comex\Schema.
 */
final class Schema
{
    public const ROL_ADMIN = 'admin';
    public const ROL_COMEX = 'comex';

    /** @return list<string> */
    public static function roles(): array
    {
        return [self::ROL_ADMIN, self::ROL_COMEX];
    }

    public static function rolValido(string $rol): bool
    {
        return in_array($rol, self::roles(), true);
    }

    /**
     * @return list<string>
     */
    public static function usuariosStatements(string $driver): array
    {
        if ($driver === 'sqlite') {
            return [
                "CREATE TABLE IF NOT EXISTS usuarios (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    nombre TEXT NOT NULL,
                    email TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    rol TEXT NOT NULL CHECK (rol IN ('admin', 'comex')),
                    activo INTEGER NOT NULL DEFAULT 1,
                    creado_en TEXT NOT NULL
                )",
                'CREATE INDEX IF NOT EXISTS idx_usuarios_email ON usuarios(email)',
                'CREATE INDEX IF NOT EXISTS idx_usuarios_rol ON usuarios(rol)',
            ];
        }
        return [
            "CREATE TABLE IF NOT EXISTS usuarios (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(160) NOT NULL,
                email VARCHAR(190) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                rol VARCHAR(16) NOT NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                creado_en DATETIME NOT NULL,
                UNIQUE KEY uq_usuarios_email (email),
                KEY idx_usuarios_rol (rol)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }

    public static function install(?PDO $pdo = null): void
    {
        \Crm\Comex\Schema::install($pdo);
    }
}
