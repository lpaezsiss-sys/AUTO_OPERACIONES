-- Condiciones comerciales de la oferta.
-- Tabla CRM: crm_cotizaciones (prefijo crm_). `fecha` del ALTER original = fecha_emision.

ALTER TABLE `crm_cotizaciones`
  ADD COLUMN `validez_oferta` VARCHAR(100) DEFAULT NULL AFTER `fecha_emision`,
  ADD COLUMN `moneda` VARCHAR(10) NOT NULL DEFAULT 'CLP' AFTER `validez_oferta`,
  ADD COLUMN `condiciones_pago` VARCHAR(150) DEFAULT NULL AFTER `moneda`,
  ADD COLUMN `plazo_entrega` VARCHAR(150) DEFAULT NULL AFTER `condiciones_pago`,
  ADD COLUMN `lugar_entrega` VARCHAR(255) DEFAULT NULL AFTER `plazo_entrega`;
