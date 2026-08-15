-- Módulo Empresa emisora / Vendedores / Comisiones
-- MySQL/MariaDB utf8mb4 — PHP 7.4 / crm.lpaezsis.cl
-- Idempotente: CREATE IF NOT EXISTS + ALTER seguro de vendedor_id.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `crm_configuracion_empresa` (
  `id` TINYINT UNSIGNED NOT NULL,
  `rut` VARCHAR(20) NOT NULL,
  `razon_social` VARCHAR(150) NOT NULL,
  `nombre_fantasia` VARCHAR(150) NULL,
  `giro` VARCHAR(255) NULL,
  `direccion` TEXT NOT NULL,
  `ciudad` VARCHAR(100) NULL,
  `region` VARCHAR(100) NULL,
  `telefono` VARCHAR(50) NULL,
  `email` VARCHAR(150) NULL,
  `sitio_web` VARCHAR(150) NULL,
  `logo_path` VARCHAR(255) NULL,
  `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_crm_config_singleton` CHECK (`id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_vendedores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT UNSIGNED NULL,
  `nombre_completo` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `telefono` VARCHAR(50) NULL,
  `comision_porcentaje` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_vendedores_email` (`email`),
  UNIQUE KEY `uq_crm_vendedores_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_comisiones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cotizacion_id` INT UNSIGNED NOT NULL,
  `vendedor_id` INT UNSIGNED NOT NULL,
  `monto_venta_neto` DECIMAL(12,2) NOT NULL,
  `porcentaje_aplicado` DECIMAL(5,2) NOT NULL,
  `monto_comision` DECIMAL(12,2) NOT NULL,
  `estado` ENUM('pendiente','aprobada','pagada','anulada') NOT NULL DEFAULT 'pendiente',
  `fecha_liquidacion` DATE NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crm_comision_cot_vend` (`cotizacion_id`, `vendedor_id`),
  KEY `ix_crm_comisiones_vendedor` (`vendedor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vendedor asignado a la cotización (encabezado PDF)
SET @crm_vend_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'crm_cotizaciones'
    AND COLUMN_NAME = 'vendedor_id'
);
SET @crm_vend_sql := IF(
  @crm_vend_col = 0,
  'ALTER TABLE `crm_cotizaciones` ADD COLUMN `vendedor_id` INT UNSIGNED NULL AFTER `ejecutivo_id`',
  'SELECT 1'
);
PREPARE crm_vend_stmt FROM @crm_vend_sql;
EXECUTE crm_vend_stmt;
DEALLOCATE PREPARE crm_vend_stmt;
