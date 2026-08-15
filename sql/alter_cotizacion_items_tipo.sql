-- Ítems de cotización: producto de inventario o servicio libre.
-- MySQL/MariaDB. producto_id permanece UNSIGNED NULL para el FK a productos.id.

ALTER TABLE `crm_cotizacion_items`
  ADD COLUMN `tipo_item` ENUM('producto', 'servicio') NOT NULL DEFAULT 'producto' AFTER `cotizacion_id`;

ALTER TABLE `crm_cotizacion_items`
  MODIFY COLUMN `producto_id` INT UNSIGNED NULL COMMENT 'NULL cuando tipo_item = servicio';
