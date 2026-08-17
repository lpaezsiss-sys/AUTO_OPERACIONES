-- Marcas representadas (logos del PDF) y selección por cotización.
-- PHP 7.4 / MySQL 8. Schema::ensureUpgrades también aplica esto.

CREATE TABLE IF NOT EXISTS `crm_marcas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(150) NOT NULL,
  `archivo` VARCHAR(255) NOT NULL,
  `activa` TINYINT(1) NOT NULL DEFAULT 1,
  `incluir_global` TINYINT(1) NOT NULL DEFAULT 1,
  `orden` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_crm_marcas_orden` (`activa`, `incluir_global`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `crm_cotizacion_marcas` (
  `cotizacion_id` INT UNSIGNED NOT NULL,
  `marca_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`cotizacion_id`, `marca_id`),
  KEY `ix_crm_cot_marcas_marca` (`marca_id`),
  CONSTRAINT `fk_crm_cot_marcas_cot`
    FOREIGN KEY (`cotizacion_id`) REFERENCES `crm_cotizaciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_crm_cot_marcas_marca`
    FOREIGN KEY (`marca_id`) REFERENCES `crm_marcas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
