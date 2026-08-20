-- Secuencia de folios COT. El correlativo NO es AUTO_INCREMENT de crm_cotizaciones.id
-- (el folio es VARCHAR COT-YYYY-NNNN). Números < 354 quedan libres para histórico
-- si no chocan con el UNIQUE de folio.

CREATE TABLE IF NOT EXISTS crm_secuencias (
  codigo VARCHAR(16) NOT NULL,
  prefijo VARCHAR(16) NOT NULL,
  siguiente INT UNSIGNED NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO crm_secuencias (codigo, prefijo, siguiente)
VALUES ('COT', 'COT', 354)
ON DUPLICATE KEY UPDATE siguiente = GREATEST(siguiente, 354);

-- Unicidad: crm_cotizaciones.folio ya tiene UNIQUE KEY uq_crm_cotizaciones_folio.
-- Si faltara (instalación antigua), Schema::ensureSecuenciaCotizaciones lo crea.
-- No ejecutar ALTER TABLE crm_cotizaciones AUTO_INCREMENT = 354: el folio no es el id.
