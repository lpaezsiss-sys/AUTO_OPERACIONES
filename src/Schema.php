<?php

declare(strict_types=1);

namespace Crm;

use PDO;

final class Schema
{
    public static function install(?PDO $pdo = null, bool $seed = true): void
    {
        $pdo = $pdo ?? crm_pdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'sqlite' : 'mysql';

        foreach (self::statements($driver) as $sql) {
            $pdo->exec($sql);
        }

        if ($seed) {
            self::seed($pdo);
        }
    }

    public static function seed(PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM crm_usuarios')->fetchColumn();
        if ($count === 0) {
            $hash = password_hash('Lpaezsis.2026', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO crm_usuarios (nombre, email, password_hash, rol, activo) VALUES (?, ?, ?, ?, 1)'
            );
            $stmt->execute(['Luis Páez', 'ivan.p@example.net', $hash, 'admin']);
            $stmt->execute(['Ejecutivo Comercial', 'nathan.k@example.net', $hash, 'vendedor']);
        }

        $prodCount = (int) $pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn();
        if ($prodCount === 0) {
            $ins = $pdo->prepare(
                'INSERT INTO productos (codigo, nombre, descripcion, stock, precio_unitario, umbral_stock, unidad, activo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
            );
            foreach (self::productosSeed() as $p) {
                $ins->execute($p);
            }
        }

        $empCount = (int) $pdo->query('SELECT COUNT(*) FROM crm_empresas')->fetchColumn();
        if ($empCount === 0) {
            self::seedDemoCrm($pdo);
        }
    }

    /** @return list<string> */
    public static function statements(string $driver): array
    {
        return $driver === 'sqlite' ? self::sqlite() : self::mysql();
    }

    /** @return list<string> */
    private static function mysql(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS productos (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS crm_usuarios (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                nombre VARCHAR(160) NOT NULL,
                email VARCHAR(190) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                rol ENUM('admin','vendedor') NOT NULL DEFAULT 'vendedor',
                activo TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_crm_usuarios_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS crm_empresas (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS crm_contactos (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS crm_oportunidades (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS crm_cotizaciones (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                folio VARCHAR(32) NOT NULL,
                empresa_id INT UNSIGNED NOT NULL,
                contacto_id INT UNSIGNED NULL,
                oportunidad_id INT UNSIGNED NULL,
                ejecutivo_id INT UNSIGNED NULL,
                estado VARCHAR(40) NOT NULL DEFAULT 'borrador',
                fecha_emision DATE NOT NULL,
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
                CONSTRAINT fk_crm_cot_empresa FOREIGN KEY (empresa_id) REFERENCES crm_empresas (id) ON DELETE CASCADE,
                CONSTRAINT fk_crm_cot_contacto FOREIGN KEY (contacto_id) REFERENCES crm_contactos (id) ON DELETE SET NULL,
                CONSTRAINT fk_crm_cot_opp FOREIGN KEY (oportunidad_id) REFERENCES crm_oportunidades (id) ON DELETE SET NULL,
                CONSTRAINT fk_crm_cot_ejecutivo FOREIGN KEY (ejecutivo_id) REFERENCES crm_usuarios (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS crm_cotizacion_items (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                cotizacion_id INT UNSIGNED NOT NULL,
                producto_id INT UNSIGNED NULL,
                codigo VARCHAR(50) NOT NULL,
                descripcion VARCHAR(300) NOT NULL,
                cantidad DECIMAL(12,2) NOT NULL DEFAULT 1,
                precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
                descuento_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
                subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
                stock_al_cotizar DECIMAL(12,2) NULL,
                PRIMARY KEY (id),
                KEY ix_crm_cot_items_cot (cotizacion_id),
                KEY ix_crm_cot_items_prod (producto_id),
                CONSTRAINT fk_crm_cot_items_cot FOREIGN KEY (cotizacion_id) REFERENCES crm_cotizaciones (id) ON DELETE CASCADE,
                CONSTRAINT fk_crm_cot_items_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS crm_actividades (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                empresa_id INT UNSIGNED NULL,
                contacto_id INT UNSIGNED NULL,
                oportunidad_id INT UNSIGNED NULL,
                cotizacion_id INT UNSIGNED NULL,
                usuario_id INT UNSIGNED NULL,
                tipo VARCHAR(40) NOT NULL DEFAULT 'nota',
                canal VARCHAR(40) NOT NULL DEFAULT 'telefono',
                titulo VARCHAR(220) NOT NULL,
                descripcion TEXT NULL,
                fecha_programada DATETIME NULL,
                fecha_completada DATETIME NULL,
                estado VARCHAR(40) NOT NULL DEFAULT 'pendiente',
                resultado VARCHAR(250) NULL,
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }

    /** @return list<string> */
    private static function sqlite(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS productos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                codigo TEXT NOT NULL UNIQUE,
                nombre TEXT NOT NULL,
                descripcion TEXT,
                stock REAL NOT NULL DEFAULT 0,
                precio_unitario REAL NOT NULL DEFAULT 0,
                umbral_stock REAL NOT NULL DEFAULT 2,
                unidad TEXT NOT NULL DEFAULT 'UN',
                activo INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS crm_usuarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                rol TEXT NOT NULL DEFAULT 'vendedor',
                activo INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS crm_empresas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                rut TEXT NOT NULL UNIQUE,
                razon_social TEXT NOT NULL,
                nombre_fantasia TEXT,
                giro TEXT,
                industria TEXT,
                region TEXT,
                comuna TEXT,
                direccion TEXT,
                telefono TEXT,
                email TEXT,
                sitio_web TEXT,
                origen TEXT NOT NULL DEFAULT 'web',
                ejecutivo_id INTEGER,
                estado TEXT NOT NULL DEFAULT 'prospecto',
                notas TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (ejecutivo_id) REFERENCES crm_usuarios(id) ON DELETE SET NULL
            )",
            "CREATE TABLE IF NOT EXISTS crm_contactos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                empresa_id INTEGER NOT NULL,
                nombre TEXT NOT NULL,
                apellido TEXT,
                cargo TEXT,
                email TEXT,
                telefono TEXT,
                whatsapp TEXT,
                canal_preferido TEXT NOT NULL DEFAULT 'email',
                es_principal INTEGER NOT NULL DEFAULT 0,
                activo INTEGER NOT NULL DEFAULT 1,
                notas TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (empresa_id) REFERENCES crm_empresas(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS crm_oportunidades (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                codigo TEXT NOT NULL UNIQUE,
                empresa_id INTEGER NOT NULL,
                contacto_id INTEGER,
                titulo TEXT NOT NULL,
                etapa TEXT NOT NULL DEFAULT 'prospecto',
                valor_estimado REAL NOT NULL DEFAULT 0,
                probabilidad INTEGER NOT NULL DEFAULT 10,
                fecha_cierre_esperada TEXT,
                ejecutivo_id INTEGER,
                origen_canal TEXT NOT NULL DEFAULT 'web',
                motivo_perdida TEXT,
                notas TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (empresa_id) REFERENCES crm_empresas(id) ON DELETE CASCADE,
                FOREIGN KEY (contacto_id) REFERENCES crm_contactos(id) ON DELETE SET NULL,
                FOREIGN KEY (ejecutivo_id) REFERENCES crm_usuarios(id) ON DELETE SET NULL
            )",
            "CREATE TABLE IF NOT EXISTS crm_cotizaciones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                folio TEXT NOT NULL UNIQUE,
                empresa_id INTEGER NOT NULL,
                contacto_id INTEGER,
                oportunidad_id INTEGER,
                ejecutivo_id INTEGER,
                estado TEXT NOT NULL DEFAULT 'borrador',
                fecha_emision TEXT NOT NULL,
                fecha_validez TEXT,
                subtotal REAL NOT NULL DEFAULT 0,
                descuento REAL NOT NULL DEFAULT 0,
                iva REAL NOT NULL DEFAULT 0,
                total REAL NOT NULL DEFAULT 0,
                notas TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (empresa_id) REFERENCES crm_empresas(id) ON DELETE CASCADE,
                FOREIGN KEY (contacto_id) REFERENCES crm_contactos(id) ON DELETE SET NULL,
                FOREIGN KEY (oportunidad_id) REFERENCES crm_oportunidades(id) ON DELETE SET NULL,
                FOREIGN KEY (ejecutivo_id) REFERENCES crm_usuarios(id) ON DELETE SET NULL
            )",
            "CREATE TABLE IF NOT EXISTS crm_cotizacion_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cotizacion_id INTEGER NOT NULL,
                producto_id INTEGER,
                codigo TEXT NOT NULL,
                descripcion TEXT NOT NULL,
                cantidad REAL NOT NULL DEFAULT 1,
                precio_unitario REAL NOT NULL DEFAULT 0,
                descuento_pct REAL NOT NULL DEFAULT 0,
                subtotal REAL NOT NULL DEFAULT 0,
                stock_al_cotizar REAL,
                FOREIGN KEY (cotizacion_id) REFERENCES crm_cotizaciones(id) ON DELETE CASCADE,
                FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
            )",
            "CREATE TABLE IF NOT EXISTS crm_actividades (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                empresa_id INTEGER,
                contacto_id INTEGER,
                oportunidad_id INTEGER,
                cotizacion_id INTEGER,
                usuario_id INTEGER,
                tipo TEXT NOT NULL DEFAULT 'nota',
                canal TEXT NOT NULL DEFAULT 'telefono',
                titulo TEXT NOT NULL,
                descripcion TEXT,
                fecha_programada TEXT,
                fecha_completada TEXT,
                estado TEXT NOT NULL DEFAULT 'pendiente',
                resultado TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (empresa_id) REFERENCES crm_empresas(id) ON DELETE SET NULL,
                FOREIGN KEY (contacto_id) REFERENCES crm_contactos(id) ON DELETE SET NULL,
                FOREIGN KEY (oportunidad_id) REFERENCES crm_oportunidades(id) ON DELETE SET NULL,
                FOREIGN KEY (cotizacion_id) REFERENCES crm_cotizaciones(id) ON DELETE SET NULL,
                FOREIGN KEY (usuario_id) REFERENCES crm_usuarios(id) ON DELETE SET NULL
            )",
        ];
    }

    /** @return list<array{0:string,1:string,2:string,3:float,4:float,5:float,6:string}> */
    public static function productosSeed(): array
    {
        return [
            ['14453', 'Sonic 100/150 Bearing Cartridge Kit', 'Sonic 100/150 Bearing Cartridge Kit (PN #14453)', 1, 1384732, 1, 'KIT'],
            ['13451', 'Correa Sonic 16 GRV 13451', 'Correa Sonic 16 GRV 13451', 10, 76337, 2, 'UN'],
            ['13514', 'Correa Sonic 16 GRV 13514', 'Correa Sonic 16 GRV 13514', 11, 65980, 2, 'UN'],
            ['13474', 'Correa Sonic 16 GRV 13474', 'Correa Sonic 16 GRV 13474', 3, 73494, 2, 'UN'],
            ['13555', 'Correa Sonic 16 GRV 13555', 'Correa Sonic 16 GRV 13555', 5, 71632, 2, 'UN'],
            ['12638', 'Sonic 85/150 Impeller', 'Sonic 85/150 Impeller (PN #12638)', 1, 987150, 1, 'UN'],
            ['14452', 'Sonic 70/85 Bearing Cartridge Kit', 'Sonic 70/85 Bearing Cartridge Kit (PN #14452)', 1, 1120636, 1, 'KIT'],
            ['13455', 'Kit Tensor Correa', 'Kit Tensor Correa', 0, 328498, 1, 'KIT'],
            ['10317', 'Filtro SONIC 85 Poly', 'Filtro SONIC 85 Poly', 1, 208746, 2, 'UN'],
            ['13900A-150', 'Sonic Pulley 13900A-150', 'Sonic Pulley (PN #13900A-150)', 2, 178569, 1, 'UN'],
            ['13900A-152', 'Sonic Pulley 13900A-152', 'Sonic Pulley (PN #13900A-152)', 0, 171405, 1, 'UN'],
            ['13900A-160', 'Sonic Pulley 13900A-160', 'Sonic Pulley (PN #13900A-160)', 1, 196945, 1, 'UN'],
            ['14454', 'Blower S85 Completo', 'Blower S85 Completo', 0, 0, 1, 'UN'],
            ['10434', 'Flexible 3" Largo 12 Pies', 'Flexible 3" Largo 12 Pies', 1, 346183, 1, 'UN'],
            ['10435', 'Flexible 4" Largo 12 Pies', 'Flexible 4" Largo 12 Pies', 0, 346183, 1, 'UN'],
            ['10976', 'Filtro Completo Con Indicador de Saturacion', 'Filtro Completo Con Indicador de Saturacion', 0, 620000, 1, 'UN'],
            ['A08-10100', 'CINTA Doble Fas CMC 10730', 'CINTA Doble Fas CMC 10730 A25 L 33m', 49, 45756, 10, 'UN'],
            ['A08-10101', 'CMC 10431 RED 25 mm x 33 mt', 'CMC 10431 RED Ancho 25 mm x 33 mt', 24, 42994, 10, 'UN'],
        ];
    }

    private static function seedDemoCrm(PDO $pdo): void
    {
        $adminId = (int) $pdo->query("SELECT id FROM crm_usuarios WHERE rol = 'admin' LIMIT 1")->fetchColumn();
        $vendId = (int) $pdo->query("SELECT id FROM crm_usuarios WHERE rol = 'vendedor' LIMIT 1")->fetchColumn();
        if ($vendId <= 0) {
            $vendId = $adminId;
        }

        $emp = $pdo->prepare(
            'INSERT INTO crm_empresas (rut, razon_social, nombre_fantasia, giro, industria, region, comuna, direccion, telefono, email, origen, ejecutivo_id, estado, notas)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $empresas = [
            ['76.543.210-3', 'Envases del Pacífico SpA', 'Envases del Pacífico', 'Fabricación de envases metálicos', 'Envasado y embalaje', 'Valparaíso', 'Quilpué', 'Av. Industrial 1200', '+56 32 211 4500', 'olivia.t@example.org', 'feria', $adminId, 'activa', 'Línea de latas 3-piece. Interés en soplado y secado.'],
            ['96.111.222-2', 'Bebidas Andinas S.A.', 'Bebidas Andinas', 'Elaboración de bebidas', 'Bebidas', 'Metropolitana de Santiago', 'San Bernardo', 'Camino Lonquén 8800', '+56 2 2555 1000', 'olivia.t@example.org', 'web', $vendId, 'activa', 'Proyecto paletizado fin de línea.'],
            ['77.888.999-4', 'Frutícola Valle Central Ltda.', 'Valle Central', 'Proceso y packing de fruta', 'Agroindustria', "Libertador General Bernardo O'Higgins", 'Rancagua', 'Ruta 5 Sur km 82', '+56 72 234 1100', 'iris.p@example.org', 'whatsapp', $vendId, 'prospecto', 'Consulta por cuchillos de aire para secado de fruta.'],
        ];
        foreach ($empresas as $row) {
            $emp->execute($row);
        }

        $ids = $pdo->query('SELECT id, razon_social FROM crm_empresas ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $byName = [];
        foreach ($ids as $r) {
            $byName[(string) $r['razon_social']] = (int) $r['id'];
        }

        $cto = $pdo->prepare(
            'INSERT INTO crm_contactos (empresa_id, nombre, apellido, cargo, email, telefono, whatsapp, canal_preferido, es_principal)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $cto->execute([$byName['Envases del Pacífico SpA'], 'María', 'Soto', 'Jefa de Mantención', 'fiona.g@example.net', '+56 9 8765 4321', '+56987654321', 'whatsapp']);
        $cto->execute([$byName['Bebidas Andinas S.A.'], 'Carlos', 'Núñez', 'Gerente de Operaciones', 'karen.d@example.net', '+56 9 6123 7788', '+56961237788', 'email']);
        $cto->execute([$byName['Frutícola Valle Central Ltda.'], 'Andrea', 'Pino', 'Jefa de Packing', 'grace.l@example.com', '+56 9 9876 2211', '+56998762211', 'whatsapp']);

        $year = date('Y');
        $opp = $pdo->prepare(
            'INSERT INTO crm_oportunidades (codigo, empresa_id, contacto_id, titulo, etapa, valor_estimado, probabilidad, fecha_cierre_esperada, ejecutivo_id, origen_canal, notas)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $c1 = (int) $pdo->query('SELECT id FROM crm_contactos WHERE empresa_id = ' . (int) $byName['Envases del Pacífico SpA'] . ' LIMIT 1')->fetchColumn();
        $c2 = (int) $pdo->query('SELECT id FROM crm_contactos WHERE empresa_id = ' . (int) $byName['Bebidas Andinas S.A.'] . ' LIMIT 1')->fetchColumn();
        $c3 = (int) $pdo->query('SELECT id FROM crm_contactos WHERE empresa_id = ' . (int) $byName['Frutícola Valle Central Ltda.'] . ' LIMIT 1')->fetchColumn();

        $opp->execute(['OPP-' . $year . '-0001', $byName['Envases del Pacífico SpA'], $c1, 'Kit de repuestos Sonic línea latas', 'propuesta', 2500000, 60, date('Y-m-d', strtotime('+21 days')), $adminId, 'feria', 'Cotizar correas + cartridge kit.']);
        $opp->execute(['OPP-' . $year . '-0002', $byName['Bebidas Andinas S.A.'], $c2, 'Blower S85 y flexibles de recambio', 'negociacion', 1800000, 75, date('Y-m-d', strtotime('+10 days')), $vendId, 'web', 'Urgencia por paro de línea.']);
        $opp->execute(['OPP-' . $year . '-0003', $byName['Frutícola Valle Central Ltda.'], $c3, 'Sistema de secado de fruta', 'calificacion', 9500000, 30, date('Y-m-d', strtotime('+45 days')), $vendId, 'whatsapp', 'Requiere visita a planta.']);

        $act = $pdo->prepare(
            'INSERT INTO crm_actividades (empresa_id, contacto_id, oportunidad_id, usuario_id, tipo, canal, titulo, descripcion, fecha_programada, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $opp1 = (int) $pdo->query("SELECT id FROM crm_oportunidades WHERE codigo = 'OPP-{$year}-0001'")->fetchColumn();
        $opp3 = (int) $pdo->query("SELECT id FROM crm_oportunidades WHERE codigo = 'OPP-{$year}-0003'")->fetchColumn();
        $act->execute([$byName['Envases del Pacífico SpA'], $c1, $opp1, $adminId, 'whatsapp', 'whatsapp', 'Seguimiento cotización Sonic', 'Confirmar recepción de propuesta.', date('Y-m-d H:i:s', strtotime('+1 day')), 'pendiente']);
        $act->execute([$byName['Frutícola Valle Central Ltda.'], $c3, $opp3, $vendId, 'visita', 'visita', 'Visita packing Rancagua', 'Relevar layout de línea de secado.', date('Y-m-d H:i:s', strtotime('+3 days')), 'pendiente']);
        $act->execute([$byName['Bebidas Andinas S.A.'], $c2, null, $vendId, 'llamada', 'telefono', 'Llamada de calificación', 'Confirmar presupuesto de recambios Q3.', crm_now(), 'completada']);
    }
}
