-- Ítems a pedido: marca, costo y flag. Amplía tipo_item.
-- PHP 7.4 / MySQL. Schema::ensureUpgrades también aplica esto.

ALTER TABLE `crm_cotizacion_items`
  MODIFY COLUMN `tipo_item` ENUM('producto','servicio','a_pedido') NOT NULL DEFAULT 'producto';

ALTER TABLE `crm_cotizacion_items`
  ADD COLUMN `es_a_pedido` TINYINT(1) NOT NULL DEFAULT 0 AFTER `tipo_item`;

ALTER TABLE `crm_cotizacion_items`
  ADD COLUMN `marca_id` INT UNSIGNED NULL AFTER `producto_id`;

ALTER TABLE `crm_cotizacion_items`
  ADD COLUMN `marca_nombre` VARCHAR(150) NULL AFTER `marca_id`;

ALTER TABLE `crm_cotizacion_items`
  ADD COLUMN `costo_unitario` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `precio_unitario`;
