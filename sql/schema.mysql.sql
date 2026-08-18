CREATE TABLE IF NOT EXISTS productos (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                codigo VARCHAR(50) NOT NULL,
                nombre VARCHAR(300) NOT NULL,
                descripcion TEXT NULL,
                stock DECIMAL(12,2) NOT NULL DEFAULT 0,
                precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
                umbral_stock DECIMAL(12,2) NOT NULL DEFAULT 2,
                unidad VARCHAR(20) NOT NULL DEFAULT 'UN',
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_productos_codigo (codigo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_usuarios (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                nombre VARCHAR(160) NOT NULL,
                email VARCHAR(190) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                rol ENUM('admin','vendedor') NOT NULL DEFAULT 'vendedor',
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_crm_usuarios_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_empresas (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                rut VARCHAR(20) NOT NULL,
                razon_social VARCHAR(220) NOT NULL,
                nombre_fantasia VARCHAR(220) NULL,
                giro VARCHAR(220) NULL,
                industria VARCHAR(80) NULL,
                region VARCHAR(80) NULL,
                comuna VARCHAR(80) NULL,
                direccion VARCHAR(400) NULL,
                telefono VARCHAR(40) NULL,
                email VARCHAR(190) NULL,
                sitio_web VARCHAR(250) NULL,
                origen VARCHAR(40) NOT NULL DEFAULT 'web',
                ejecutivo_id INT UNSIGNED NULL,
                estado ENUM('prospecto','activa','inactiva') NOT NULL DEFAULT 'prospecto',
                notas TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_crm_empresas_rut (rut),
                KEY ix_crm_empresas_ejecutivo (ejecutivo_id),
                CONSTRAINT fk_crm_empresas_ejecutivo FOREIGN KEY (ejecutivo_id) REFERENCES crm_usuarios (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_contactos (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                empresa_id INT UNSIGNED NOT NULL,
                nombre VARCHAR(120) NOT NULL,
                apellido VARCHAR(120) NULL,
                cargo VARCHAR(160) NULL,
                email VARCHAR(190) NULL,
                telefono VARCHAR(40) NULL,
                whatsapp VARCHAR(40) NULL,
                canal_preferido VARCHAR(40) NOT NULL DEFAULT 'email',
                es_principal TINYINT(1) NOT NULL DEFAULT 0,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                notas TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY ix_crm_contactos_empresa (empresa_id),
                CONSTRAINT fk_crm_contactos_empresa FOREIGN KEY (empresa_id) REFERENCES crm_empresas (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_oportunidades (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                codigo VARCHAR(32) NOT NULL,
                empresa_id INT UNSIGNED NOT NULL,
                contacto_id INT UNSIGNED NULL,
                titulo VARCHAR(220) NOT NULL,
                etapa VARCHAR(40) NOT NULL DEFAULT 'prospecto',
                valor_estimado DECIMAL(14,2) NOT NULL DEFAULT 0,
                probabilidad TINYINT UNSIGNED NOT NULL DEFAULT 10,
                fecha_cierre_esperada DATE NULL,
                ejecutivo_id INT UNSIGNED NULL,
                origen_canal VARCHAR(40) NOT NULL DEFAULT 'web',
                motivo_perdida VARCHAR(250) NULL,
                notas TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_crm_oportunidades_codigo (codigo),
                KEY ix_crm_opp_empresa (empresa_id),
                KEY ix_crm_opp_etapa (etapa),
                CONSTRAINT fk_crm_opp_empresa FOREIGN KEY (empresa_id) REFERENCES crm_empresas (id) ON DELETE CASCADE,
                CONSTRAINT fk_crm_opp_contacto FOREIGN KEY (contacto_id) REFERENCES crm_contactos (id) ON DELETE SET NULL,
                CONSTRAINT fk_crm_opp_ejecutivo FOREIGN KEY (ejecutivo_id) REFERENCES crm_usuarios (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_cotizaciones (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                folio VARCHAR(32) NOT NULL,
                empresa_id INT UNSIGNED NOT NULL,
                contacto_id INT UNSIGNED NULL,
                oportunidad_id INT UNSIGNED NULL,
                ejecutivo_id INT UNSIGNED NULL,
                vendedor_id INT UNSIGNED NULL,
                estado VARCHAR(40) NOT NULL DEFAULT 'borrador',
                fecha_emision DATE NOT NULL,
                validez_oferta VARCHAR(100) NULL,
                moneda VARCHAR(10) NOT NULL DEFAULT 'CLP',
                condiciones_pago VARCHAR(150) NULL,
                plazo_entrega VARCHAR(150) NULL,
                lugar_entrega VARCHAR(255) NULL,
                fecha_validez DATE NULL,
                subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
                descuento DECIMAL(14,2) NOT NULL DEFAULT 0,
                iva DECIMAL(14,2) NOT NULL DEFAULT 0,
                total DECIMAL(14,2) NOT NULL DEFAULT 0,
                notas TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_crm_cotizaciones_folio (folio),
                KEY ix_crm_cot_empresa (empresa_id),
                KEY ix_crm_cot_empresa_id (empresa_id, id),
                CONSTRAINT fk_crm_cot_empresa FOREIGN KEY (empresa_id) REFERENCES crm_empresas (id) ON DELETE CASCADE,
                CONSTRAINT fk_crm_cot_contacto FOREIGN KEY (contacto_id) REFERENCES crm_contactos (id) ON DELETE SET NULL,
                CONSTRAINT fk_crm_cot_opp FOREIGN KEY (oportunidad_id) REFERENCES crm_oportunidades (id) ON DELETE SET NULL,
                CONSTRAINT fk_crm_cot_ejecutivo FOREIGN KEY (ejecutivo_id) REFERENCES crm_usuarios (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_cotizacion_items (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                cotizacion_id INT UNSIGNED NOT NULL,
                tipo_item ENUM('producto','servicio','a_pedido') NOT NULL DEFAULT 'producto',
                es_a_pedido TINYINT(1) NOT NULL DEFAULT 0,
                producto_id INT UNSIGNED NULL,
                marca_id INT UNSIGNED NULL,
                marca_nombre VARCHAR(150) NULL,
                codigo VARCHAR(50) NOT NULL,
                descripcion VARCHAR(300) NOT NULL,
                descripcion_detallada TEXT NULL,
                imagen_url VARCHAR(500) NULL,
                cantidad DECIMAL(12,2) NOT NULL DEFAULT 1,
                precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
                costo_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
                descuento_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
                subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
                stock_al_cotizar DECIMAL(12,2) NULL,
                PRIMARY KEY (id),
                KEY ix_crm_cot_items_cot (cotizacion_id),
                KEY ix_crm_cot_items_prod (producto_id),
                KEY ix_crm_cot_items_prod_cot (producto_id, cotizacion_id),
                CONSTRAINT fk_crm_cot_items_cot FOREIGN KEY (cotizacion_id) REFERENCES crm_cotizaciones (id) ON DELETE CASCADE,
                CONSTRAINT fk_crm_cot_items_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_marcas (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                nombre VARCHAR(150) NOT NULL,
                archivo VARCHAR(255) NOT NULL,
                activa TINYINT(1) NOT NULL DEFAULT 1,
                incluir_global TINYINT(1) NOT NULL DEFAULT 1,
                orden INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY ix_crm_marcas_orden (activa, incluir_global, orden)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_cotizacion_marcas (
                cotizacion_id INT UNSIGNED NOT NULL,
                marca_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (cotizacion_id, marca_id),
                KEY ix_crm_cot_marcas_marca (marca_id),
                CONSTRAINT fk_crm_cot_marcas_cot FOREIGN KEY (cotizacion_id) REFERENCES crm_cotizaciones (id) ON DELETE CASCADE,
                CONSTRAINT fk_crm_cot_marcas_marca FOREIGN KEY (marca_id) REFERENCES crm_marcas (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_configuracion_empresa (
                id TINYINT UNSIGNED NOT NULL,
                rut VARCHAR(20) NOT NULL,
                razon_social VARCHAR(150) NOT NULL,
                nombre_fantasia VARCHAR(150) NULL,
                giro VARCHAR(255) NULL,
                direccion TEXT NOT NULL,
                ciudad VARCHAR(100) NULL,
                region VARCHAR(100) NULL,
                telefono VARCHAR(50) NULL,
                email VARCHAR(150) NULL,
                sitio_web VARCHAR(150) NULL,
                logo_path VARCHAR(255) NULL,
                actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                CONSTRAINT chk_crm_config_singleton CHECK (id = 1)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_vendedores (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id INT UNSIGNED NULL,
                nombre_completo VARCHAR(150) NOT NULL,
                email VARCHAR(150) NOT NULL,
                telefono VARCHAR(50) NULL,
                comision_porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_crm_vendedores_email (email),
                UNIQUE KEY uq_crm_vendedores_usuario (usuario_id),
                CONSTRAINT fk_crm_vendedores_usuario FOREIGN KEY (usuario_id) REFERENCES crm_usuarios (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_comisiones (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                cotizacion_id INT UNSIGNED NOT NULL,
                vendedor_id INT UNSIGNED NOT NULL,
                monto_venta_neto DECIMAL(12,2) NOT NULL,
                porcentaje_aplicado DECIMAL(5,2) NOT NULL,
                monto_comision DECIMAL(12,2) NOT NULL,
                estado ENUM('pendiente','aprobada','pagada','anulada') NOT NULL DEFAULT 'pendiente',
                fecha_liquidacion DATE NULL,
                creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_crm_comision_cot_vend (cotizacion_id, vendedor_id),
                KEY ix_crm_comisiones_vendedor (vendedor_id),
                CONSTRAINT fk_crm_comisiones_cot FOREIGN KEY (cotizacion_id) REFERENCES crm_cotizaciones (id) ON DELETE CASCADE,
                CONSTRAINT fk_crm_comisiones_vendedor FOREIGN KEY (vendedor_id) REFERENCES crm_vendedores (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crm_actividades (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                empresa_id INT UNSIGNED NULL,
                contacto_id INT UNSIGNED NULL,
                oportunidad_id INT UNSIGNED NULL,
                cotizacion_id INT UNSIGNED NULL,
                vendedor_id INT UNSIGNED NULL,
                usuario_id INT UNSIGNED NULL,
                tipo VARCHAR(40) NOT NULL DEFAULT 'nota',
                canal VARCHAR(40) NOT NULL DEFAULT 'telefono',
                titulo VARCHAR(220) NOT NULL,
                descripcion TEXT NULL,
                fecha_programada DATETIME NULL,
                fecha_completada DATETIME NULL,
                estado VARCHAR(40) NOT NULL DEFAULT 'pendiente',
                resultado VARCHAR(250) NULL,
                creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY ix_crm_act_empresa (empresa_id),
                KEY ix_crm_act_estado (estado, fecha_programada),
                CONSTRAINT fk_crm_act_empresa FOREIGN KEY (empresa_id) REFERENCES crm_empresas (id) ON DELETE SET NULL,
                CONSTRAINT fk_crm_act_contacto FOREIGN KEY (contacto_id) REFERENCES crm_contactos (id) ON DELETE SET NULL,
                CONSTRAINT fk_crm_act_opp FOREIGN KEY (oportunidad_id) REFERENCES crm_oportunidades (id) ON DELETE SET NULL,
                CONSTRAINT fk_crm_act_cot FOREIGN KEY (cotizacion_id) REFERENCES crm_cotizaciones (id) ON DELETE SET NULL,
                CONSTRAINT fk_crm_act_usuario FOREIGN KEY (usuario_id) REFERENCES crm_usuarios (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
