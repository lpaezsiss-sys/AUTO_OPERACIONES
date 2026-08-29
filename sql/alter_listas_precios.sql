-- Listas de precios (ajuste porcentual) + índices de historial.
-- Local / upgrade. No modifica `productos`.

CREATE TABLE IF NOT EXISTS crm_listas_precios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(160) NOT NULL,
  porcentaje_ajuste DECIMAL(8,2) NOT NULL DEFAULT 0,
  es_default TINYINT(1) NOT NULL DEFAULT 0,
  estado VARCHAR(20) NOT NULL DEFAULT 'activa',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_crm_listas_estado (estado, es_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE crm_empresas
  ADD COLUMN lista_precio_id INT UNSIGNED NULL;

ALTER TABLE crm_cotizaciones
  ADD COLUMN lista_precio_id INT UNSIGNED NULL;

CREATE INDEX ix_crm_cot_empresa_id ON crm_cotizaciones (empresa_id, id);
CREATE INDEX ix_crm_cot_items_prod_cot ON crm_cotizacion_items (producto_id, cotizacion_id);
