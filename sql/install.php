<?php

declare(strict_types=1);

/**
 * Instala tablas CRM (prefijo crm_) y, si no existe, la tabla productos de inventario.
 *
 *   php sql/install.php
 */
require dirname(__DIR__) . '/includes/bootstrap.php';

\Crm\Schema::install();
echo "OK: esquema CRM instalado (" . crm_pdo_driver() . ")\n";
