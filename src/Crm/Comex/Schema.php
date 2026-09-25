<?php

declare(strict_types=1);

namespace Crm\Comex;

use Crm\Database\Connection;
use PDO;

final class Schema
{
    public static function install(?PDO $pdo = null): void
    {
        $pdo = $pdo ?? Connection::app();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'sqlite' : 'mysql';
        foreach (self::statements($driver) as $sql) {
            $pdo->exec($sql);
        }
    }

    /** @return list<string> */
    public static function statements(string $driver): array
    {
        if ($driver === 'sqlite') {
            return [
                'CREATE TABLE IF NOT EXISTS comex_fichas (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    sku TEXT NOT NULL UNIQUE,
                    inventario_id TEXT,
                    nombre TEXT NOT NULL,
                    descripcion TEXT DEFAULT \'\',
                    partida_arancelaria TEXT DEFAULT \'\',
                    origen_pais TEXT DEFAULT \'\',
                    unidad TEXT DEFAULT \'UN\',
                    imagen_path TEXT DEFAULT \'\',
                    synced_at TEXT,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )',
                'CREATE TABLE IF NOT EXISTS comex_operaciones (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tipo TEXT NOT NULL,
                    folio TEXT NOT NULL UNIQUE,
                    estado TEXT NOT NULL DEFAULT \'borrador\',
                    fecha TEXT NOT NULL,
                    referencia TEXT DEFAULT \'\',
                    pdf_path TEXT DEFAULT \'\',
                    synced_at TEXT,
                    movimiento_ids TEXT DEFAULT \'\',
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )',
                'CREATE TABLE IF NOT EXISTS comex_operacion_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    operacion_id INTEGER NOT NULL,
                    ficha_id INTEGER,
                    sku TEXT NOT NULL,
                    inventario_id TEXT,
                    descripcion TEXT DEFAULT \'\',
                    cantidad REAL NOT NULL,
                    precio_unitario REAL NOT NULL DEFAULT 0,
                    imagen_path TEXT DEFAULT \'\',
                    FOREIGN KEY (operacion_id) REFERENCES comex_operaciones(id)
                )',
                'CREATE INDEX IF NOT EXISTS idx_comex_items_op ON comex_operacion_items(operacion_id)',
                'CREATE INDEX IF NOT EXISTS idx_comex_items_sku ON comex_operacion_items(sku)',
            ];
        }

        return [
            'CREATE TABLE IF NOT EXISTS comex_fichas (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sku VARCHAR(64) NOT NULL,
                inventario_id VARCHAR(64) NULL,
                nombre VARCHAR(255) NOT NULL,
                descripcion TEXT,
                partida_arancelaria VARCHAR(32) DEFAULT \'\',
                origen_pais VARCHAR(8) DEFAULT \'\',
                unidad VARCHAR(16) DEFAULT \'UN\',
                imagen_path VARCHAR(255) DEFAULT \'\',
                synced_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_comex_fichas_sku (sku)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS comex_operaciones (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tipo VARCHAR(16) NOT NULL,
                folio VARCHAR(32) NOT NULL,
                estado VARCHAR(16) NOT NULL DEFAULT \'borrador\',
                fecha DATE NOT NULL,
                referencia VARCHAR(255) DEFAULT \'\',
                pdf_path VARCHAR(255) DEFAULT \'\',
                synced_at DATETIME NULL,
                movimiento_ids TEXT,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_comex_ops_folio (folio)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS comex_operacion_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                operacion_id INT UNSIGNED NOT NULL,
                ficha_id INT UNSIGNED NULL,
                sku VARCHAR(64) NOT NULL,
                inventario_id VARCHAR(64) NULL,
                descripcion VARCHAR(255) DEFAULT \'\',
                cantidad DECIMAL(18,4) NOT NULL,
                precio_unitario DECIMAL(18,4) NOT NULL DEFAULT 0,
                imagen_path VARCHAR(255) DEFAULT \'\',
                KEY idx_comex_items_op (operacion_id),
                KEY idx_comex_items_sku (sku),
                CONSTRAINT fk_comex_items_op FOREIGN KEY (operacion_id) REFERENCES comex_operaciones(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];
    }
}
