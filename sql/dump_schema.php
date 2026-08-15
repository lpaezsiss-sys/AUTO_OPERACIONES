<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/polyfills.php';
require dirname(__DIR__) . '/src/Schema.php';

$sql = implode(";\n\n", \Crm\Schema::statements('mysql')) . ";\n";
file_put_contents(__DIR__ . '/schema.mysql.sql', $sql);
echo "Wrote sql/schema.mysql.sql\n";
