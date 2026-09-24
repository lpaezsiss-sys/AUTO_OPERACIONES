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
        self::ensureUpgrades($pdo, $driver);
    }

    /**
     * Columnas nuevas sobre instalaciones ya creadas (CREATE TABLE IF NOT EXISTS no altera).
     */
    public static function ensureUpgrades(?PDO $pdo = null, ?string $driver = null): void
    {
        $pdo = $pdo ?? Connection::app();
        $driver = $driver ?? ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'sqlite' : 'mysql');
        $haveItems = self::columnMap($pdo, 'comex_operacion_items', $driver);
        foreach (self::itemUpgradeStatements($driver, $haveItems) as $sql) {
            $pdo->exec($sql);
        }
        $haveOps = self::columnMap($pdo, 'comex_operaciones', $driver);
        foreach (self::operacionUpgradeStatements($driver, $haveOps) as $sql) {
            $pdo->exec($sql);
        }
    }

    /**
     * @param array<string, true> $have
     * @return list<string>
     */
    private static function itemUpgradeStatements(string $driver, array $have): array
    {
        $out = [];
        if ($driver === 'sqlite') {
            if (!isset($have['origen'])) {
                $out[] = "ALTER TABLE comex_operacion_items ADD COLUMN origen TEXT DEFAULT 'inventario'";
            }
            if (!isset($have['is_custom'])) {
                $out[] = 'ALTER TABLE comex_operacion_items ADD COLUMN is_custom INTEGER DEFAULT 0';
            }
            if (!isset($have['sku_temporal'])) {
                $out[] = "ALTER TABLE comex_operacion_items ADD COLUMN sku_temporal TEXT DEFAULT ''";
            }
            if (!isset($have['movimiento_id'])) {
                $out[] = "ALTER TABLE comex_operacion_items ADD COLUMN movimiento_id TEXT DEFAULT ''";
            }
            return $out;
        }
        if (!isset($have['origen'])) {
            $out[] = "ALTER TABLE comex_operacion_items ADD COLUMN origen VARCHAR(16) NOT NULL DEFAULT 'inventario'";
        }
        if (!isset($have['is_custom'])) {
            $out[] = 'ALTER TABLE comex_operacion_items ADD COLUMN is_custom TINYINT(1) NOT NULL DEFAULT 0';
        }
        if (!isset($have['sku_temporal'])) {
            $out[] = "ALTER TABLE comex_operacion_items ADD COLUMN sku_temporal VARCHAR(64) DEFAULT ''";
        }
        if (!isset($have['movimiento_id'])) {
            $out[] = "ALTER TABLE comex_operacion_items ADD COLUMN movimiento_id VARCHAR(64) DEFAULT ''";
        }
        return $out;
    }

    /**
     * @param array<string, true> $have
     * @return list<string>
     */
    private static function operacionUpgradeStatements(string $driver, array $have): array
    {
        $out = [];
        if ($driver === 'sqlite') {
            if (!isset($have['nombre'])) {
                $out[] = "ALTER TABLE comex_operaciones ADD COLUMN nombre TEXT DEFAULT ''";
            }
            if (!isset($have['proveedor'])) {
                $out[] = "ALTER TABLE comex_operaciones ADD COLUMN proveedor TEXT DEFAULT ''";
            }
            if (!isset($have['moneda_base'])) {
                $out[] = "ALTER TABLE comex_operaciones ADD COLUMN moneda_base TEXT DEFAULT 'USD'";
            }
            return $out;
        }
        if (!isset($have['nombre'])) {
            $out[] = "ALTER TABLE comex_operaciones ADD COLUMN nombre VARCHAR(255) NOT NULL DEFAULT ''";
        }
        if (!isset($have['proveedor'])) {
            $out[] = "ALTER TABLE comex_operaciones ADD COLUMN proveedor VARCHAR(160) NOT NULL DEFAULT ''";
        }
        if (!isset($have['moneda_base'])) {
            $out[] = "ALTER TABLE comex_operaciones ADD COLUMN moneda_base VARCHAR(8) NOT NULL DEFAULT 'USD'";
        }
        return $out;
    }

    /** @return array<string, true> */
    private static function columnMap(PDO $pdo, string $table, string $driver): array
    {
        $out = [];
        if ($driver === 'sqlite') {
            $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
            foreach (is_array($rows) ? $rows : [] as $row) {
                if (is_array($row) && isset($row['name'])) {
                    $out[strtolower((string) $row['name'])] = true;
                }
            }
            return $out;
        }
        $rows = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`')->fetchAll(PDO::FETCH_ASSOC);
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && isset($row['Field'])) {
                $out[strtolower((string) $row['Field'])] = true;
            }
        }
        return $out;
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
                    nombre TEXT DEFAULT \'\',
                    proveedor TEXT DEFAULT \'\',
                    referencia TEXT DEFAULT \'\',
                    moneda_base TEXT NOT NULL DEFAULT \'USD\',
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
                    origen TEXT NOT NULL DEFAULT \'inventario\',
                    is_custom INTEGER NOT NULL DEFAULT 0,
                    sku_temporal TEXT DEFAULT \'\',
                    movimiento_id TEXT DEFAULT \'\',
                    FOREIGN KEY (operacion_id) REFERENCES comex_operaciones(id)
                )',
                'CREATE INDEX IF NOT EXISTS idx_comex_items_op ON comex_operacion_items(operacion_id)',
                'CREATE INDEX IF NOT EXISTS idx_comex_items_sku ON comex_operacion_items(sku)',
                'CREATE TABLE IF NOT EXISTS comex_landed_cost (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    operacion_id INTEGER NOT NULL,
                    version TEXT NOT NULL,
                    moneda_origen TEXT NOT NULL DEFAULT \'USD\',
                    tipo_cambio_usd REAL NOT NULL DEFAULT 0,
                    tipo_cambio_eur REAL NOT NULL DEFAULT 0,
                    iva_pct REAL NOT NULL DEFAULT 19,
                    notas TEXT DEFAULT \'\',
                    pdf_path TEXT DEFAULT \'\',
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    UNIQUE (operacion_id, version)
                )',
                'CREATE TABLE IF NOT EXISTS comex_landed_gastos (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    landed_id INTEGER NOT NULL,
                    codigo TEXT NOT NULL,
                    nombre TEXT NOT NULL,
                    ambito TEXT NOT NULL,
                    moneda TEXT NOT NULL,
                    monto REAL NOT NULL DEFAULT 0,
                    monto_clp REAL NOT NULL DEFAULT 0,
                    cif INTEGER NOT NULL DEFAULT 0,
                    FOREIGN KEY (landed_id) REFERENCES comex_landed_cost(id)
                )',
                'CREATE TABLE IF NOT EXISTS comex_landed_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    landed_id INTEGER NOT NULL,
                    operacion_item_id INTEGER DEFAULT 0,
                    sku TEXT NOT NULL,
                    descripcion TEXT DEFAULT \'\',
                    cantidad REAL NOT NULL,
                    fob_unitario REAL NOT NULL DEFAULT 0,
                    fob_origen REAL NOT NULL DEFAULT 0,
                    fob_clp REAL NOT NULL DEFAULT 0,
                    share REAL NOT NULL DEFAULT 0,
                    gastos_cif_clp REAL NOT NULL DEFAULT 0,
                    cif_clp REAL NOT NULL DEFAULT 0,
                    iva_clp REAL NOT NULL DEFAULT 0,
                    gastos_locales_clp REAL NOT NULL DEFAULT 0,
                    landed_unitario_clp REAL NOT NULL DEFAULT 0,
                    landed_total_clp REAL NOT NULL DEFAULT 0,
                    FOREIGN KEY (landed_id) REFERENCES comex_landed_cost(id)
                )',
                'CREATE INDEX IF NOT EXISTS idx_landed_op ON comex_landed_cost(operacion_id)',
                'CREATE TABLE IF NOT EXISTS comex_operacion_etapas (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    operacion_id INTEGER NOT NULL,
                    codigo TEXT NOT NULL,
                    nombre TEXT NOT NULL,
                    fase TEXT NOT NULL,
                    orden INTEGER NOT NULL,
                    estado TEXT NOT NULL DEFAULT \'PENDING\',
                    responsable TEXT DEFAULT \'\',
                    fecha_estimada TEXT,
                    fecha_real TEXT,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    UNIQUE (operacion_id, codigo),
                    FOREIGN KEY (operacion_id) REFERENCES comex_operaciones(id)
                )',
                'CREATE INDEX IF NOT EXISTS idx_etapas_op ON comex_operacion_etapas(operacion_id)',
                'CREATE INDEX IF NOT EXISTS idx_etapas_estado ON comex_operacion_etapas(estado)',
                'CREATE TABLE IF NOT EXISTS comex_etapa_bitacora (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    etapa_id INTEGER NOT NULL,
                    comentario TEXT NOT NULL,
                    autor TEXT NOT NULL DEFAULT \'\',
                    created_at TEXT NOT NULL,
                    FOREIGN KEY (etapa_id) REFERENCES comex_operacion_etapas(id)
                )',
                'CREATE INDEX IF NOT EXISTS idx_bitacora_etapa ON comex_etapa_bitacora(etapa_id)',
                'CREATE TABLE IF NOT EXISTS comex_documentos (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    operacion_id INTEGER NOT NULL,
                    tipo TEXT NOT NULL,
                    nombre_original TEXT NOT NULL,
                    mime TEXT NOT NULL,
                    size_bytes INTEGER NOT NULL DEFAULT 0,
                    path TEXT NOT NULL,
                    usuario TEXT NOT NULL DEFAULT \'COMEX\',
                    created_at TEXT NOT NULL,
                    FOREIGN KEY (operacion_id) REFERENCES comex_operaciones(id) ON DELETE CASCADE
                )',
                'CREATE INDEX IF NOT EXISTS idx_comex_docs_op ON comex_documentos(operacion_id, created_at)',
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
                nombre VARCHAR(255) DEFAULT \'\',
                proveedor VARCHAR(160) DEFAULT \'\',
                referencia VARCHAR(255) DEFAULT \'\',
                moneda_base VARCHAR(8) NOT NULL DEFAULT \'USD\',
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
                origen VARCHAR(16) NOT NULL DEFAULT \'inventario\',
                is_custom TINYINT(1) NOT NULL DEFAULT 0,
                sku_temporal VARCHAR(64) DEFAULT \'\',
                movimiento_id VARCHAR(64) DEFAULT \'\',
                KEY idx_comex_items_op (operacion_id),
                KEY idx_comex_items_sku (sku),
                CONSTRAINT fk_comex_items_op FOREIGN KEY (operacion_id) REFERENCES comex_operaciones(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS comex_landed_cost (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                operacion_id INT UNSIGNED NOT NULL,
                version VARCHAR(16) NOT NULL,
                moneda_origen VARCHAR(8) NOT NULL DEFAULT \'USD\',
                tipo_cambio_usd DECIMAL(18,6) NOT NULL DEFAULT 0,
                tipo_cambio_eur DECIMAL(18,6) NOT NULL DEFAULT 0,
                iva_pct DECIMAL(8,4) NOT NULL DEFAULT 19,
                notas TEXT,
                pdf_path VARCHAR(255) DEFAULT \'\',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_landed_op_ver (operacion_id, version),
                KEY idx_landed_op (operacion_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS comex_landed_gastos (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                landed_id INT UNSIGNED NOT NULL,
                codigo VARCHAR(32) NOT NULL,
                nombre VARCHAR(120) NOT NULL,
                ambito VARCHAR(16) NOT NULL,
                moneda VARCHAR(8) NOT NULL,
                monto DECIMAL(18,4) NOT NULL DEFAULT 0,
                monto_clp DECIMAL(18,4) NOT NULL DEFAULT 0,
                cif TINYINT(1) NOT NULL DEFAULT 0,
                KEY idx_landed_gastos (landed_id),
                CONSTRAINT fk_landed_gastos FOREIGN KEY (landed_id) REFERENCES comex_landed_cost(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS comex_landed_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                landed_id INT UNSIGNED NOT NULL,
                operacion_item_id INT UNSIGNED DEFAULT 0,
                sku VARCHAR(64) NOT NULL,
                descripcion VARCHAR(255) DEFAULT \'\',
                cantidad DECIMAL(18,4) NOT NULL,
                fob_unitario DECIMAL(18,4) NOT NULL DEFAULT 0,
                fob_origen DECIMAL(18,4) NOT NULL DEFAULT 0,
                fob_clp DECIMAL(18,4) NOT NULL DEFAULT 0,
                share DECIMAL(18,8) NOT NULL DEFAULT 0,
                gastos_cif_clp DECIMAL(18,4) NOT NULL DEFAULT 0,
                cif_clp DECIMAL(18,4) NOT NULL DEFAULT 0,
                iva_clp DECIMAL(18,4) NOT NULL DEFAULT 0,
                gastos_locales_clp DECIMAL(18,4) NOT NULL DEFAULT 0,
                landed_unitario_clp DECIMAL(18,4) NOT NULL DEFAULT 0,
                landed_total_clp DECIMAL(18,4) NOT NULL DEFAULT 0,
                KEY idx_landed_items (landed_id),
                CONSTRAINT fk_landed_items FOREIGN KEY (landed_id) REFERENCES comex_landed_cost(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS comex_operacion_etapas (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                operacion_id INT UNSIGNED NOT NULL,
                codigo VARCHAR(32) NOT NULL,
                nombre VARCHAR(160) NOT NULL,
                fase VARCHAR(16) NOT NULL,
                orden TINYINT UNSIGNED NOT NULL,
                estado VARCHAR(16) NOT NULL DEFAULT \'PENDING\',
                responsable VARCHAR(120) DEFAULT \'\',
                fecha_estimada DATE NULL,
                fecha_real DATE NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_etapa_op_cod (operacion_id, codigo),
                KEY idx_etapas_op (operacion_id),
                KEY idx_etapas_estado (estado),
                CONSTRAINT fk_etapas_op FOREIGN KEY (operacion_id) REFERENCES comex_operaciones(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS comex_etapa_bitacora (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                etapa_id INT UNSIGNED NOT NULL,
                comentario TEXT NOT NULL,
                autor VARCHAR(120) NOT NULL DEFAULT \'\',
                created_at DATETIME NOT NULL,
                KEY idx_bitacora_etapa (etapa_id),
                CONSTRAINT fk_bitacora_etapa FOREIGN KEY (etapa_id) REFERENCES comex_operacion_etapas(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            'CREATE TABLE IF NOT EXISTS comex_documentos (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                operacion_id INT UNSIGNED NOT NULL,
                tipo VARCHAR(32) NOT NULL,
                nombre_original VARCHAR(255) NOT NULL,
                mime VARCHAR(120) NOT NULL,
                size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
                path VARCHAR(255) NOT NULL,
                usuario VARCHAR(120) NOT NULL DEFAULT \'COMEX\',
                created_at DATETIME NOT NULL,
                KEY idx_comex_docs_op (operacion_id, created_at),
                CONSTRAINT fk_docs_op FOREIGN KEY (operacion_id) REFERENCES comex_operaciones(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        ];
    }
}
