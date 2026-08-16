-- Módulo Actividades, Agenda y Seguimiento de Postventa
-- MySQL/MariaDB utf8mb4 — PHP 7.4 / crm.lpaezsis.cl
-- Idempotente: CREATE IF NOT EXISTS + ALTER de vendedor_id y creado_en.
--
-- tipo: llamada, reunion, correo, nota, tarea (también se aceptan email/whatsapp/visita)
-- estado: pendiente, realizada, cancelada (completada se trata como realizada)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `crm_actividades` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo` VARCHAR(40) NOT NULL DEFAULT 'nota',
  `titulo` VARCHAR(220) NOT NULL,
  `descripcion` TEXT NULL,
  `fecha_programada` DATETIME NULL,
  `estado` VARCHAR(40) NOT NULL DEFAULT 'pendiente',
  `oportunidad_id` INT UNSIGNED NULL,
  `cotizacion_id` INT UNSIGNED NULL,
  `empresa_id` INT UNSIGNED NULL,
  `vendedor_id` INT UNSIGNED NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `contacto_id` INT UNSIGNED NULL,
  `usuario_id` INT UNSIGNED NULL,
  `canal` VARCHAR(40) NOT NULL DEFAULT 'telefono',
  `fecha_completada` DATETIME NULL,
  `resultado` VARCHAR(250) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_crm_act_empresa` (`empresa_id`),
  KEY `ix_crm_act_vendedor` (`vendedor_id`),
  KEY `ix_crm_act_estado` (`estado`, `fecha_programada`),
  KEY `ix_crm_act_cot` (`cotizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @crm_act_vend := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'crm_actividades'
    AND COLUMN_NAME = 'vendedor_id'
);
SET @crm_act_vend_sql := IF(
  @crm_act_vend = 0,
  'ALTER TABLE `crm_actividades` ADD COLUMN `vendedor_id` INT UNSIGNED NULL AFTER `empresa_id`',
  'SELECT 1'
);
PREPARE crm_act_vend_stmt FROM @crm_act_vend_sql;
EXECUTE crm_act_vend_stmt;
DEALLOCATE PREPARE crm_act_vend_stmt;

SET @crm_act_creado := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'crm_actividades'
    AND COLUMN_NAME = 'creado_en'
);
SET @crm_act_creado_sql := IF(
  @crm_act_creado = 0,
  'ALTER TABLE `crm_actividades` ADD COLUMN `creado_en` DATETIME NULL AFTER `vendedor_id`',
  'SELECT 1'
);
PREPARE crm_act_creado_stmt FROM @crm_act_creado_sql;
EXECUTE crm_act_creado_stmt;
DEALLOCATE PREPARE crm_act_creado_stmt;

UPDATE `crm_actividades`
   SET `creado_en` = `created_at`
 WHERE `creado_en` IS NULL AND `created_at` IS NOT NULL;
