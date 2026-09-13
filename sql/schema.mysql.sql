-- Esquema COMEX (MySQL utf8mb4) — fichas y operaciones vinculadas a prod.db
-- CREATE DATABASE sistem29_comex CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comex_fichas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(64) NOT NULL,
    inventario_id VARCHAR(64) NULL,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    partida_arancelaria VARCHAR(32) DEFAULT '',
    origen_pais VARCHAR(8) DEFAULT '',
    unidad VARCHAR(16) DEFAULT 'UN',
    imagen_path VARCHAR(255) DEFAULT '',
    synced_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_comex_fichas_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comex_operaciones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(16) NOT NULL,
    folio VARCHAR(32) NOT NULL,
    estado VARCHAR(16) NOT NULL DEFAULT 'borrador',
    fecha DATE NOT NULL,
    referencia VARCHAR(255) DEFAULT '',
    pdf_path VARCHAR(255) DEFAULT '',
    synced_at DATETIME NULL,
    movimiento_ids TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_comex_ops_folio (folio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comex_operacion_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operacion_id INT UNSIGNED NOT NULL,
    ficha_id INT UNSIGNED NULL,
    sku VARCHAR(64) NOT NULL,
    inventario_id VARCHAR(64) NULL,
    descripcion VARCHAR(255) DEFAULT '',
    cantidad DECIMAL(18,4) NOT NULL,
    precio_unitario DECIMAL(18,4) NOT NULL DEFAULT 0,
    imagen_path VARCHAR(255) DEFAULT '',
    KEY idx_comex_items_op (operacion_id),
    KEY idx_comex_items_sku (sku),
    CONSTRAINT fk_comex_items_op FOREIGN KEY (operacion_id) REFERENCES comex_operaciones(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
