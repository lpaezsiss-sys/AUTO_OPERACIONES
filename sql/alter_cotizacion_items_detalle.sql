-- Descripción detallada e imagen por ítem de cotización.
-- PHP 7.4 / MySQL. Schema::ensureUpgrades también aplica esto.

ALTER TABLE `crm_cotizacion_items`
  ADD COLUMN `descripcion_detallada` TEXT NULL AFTER `descripcion`;

ALTER TABLE `crm_cotizacion_items`
  ADD COLUMN `imagen_url` VARCHAR(500) NULL AFTER `descripcion_detallada`;
