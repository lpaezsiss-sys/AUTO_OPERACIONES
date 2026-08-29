<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

\Crm\Http::handle(static function () {
    $okDb = false;
    $driver = null;
    $dbError = null;
    try {
        crm_pdo()->query('SELECT 1');
        $okDb = true;
        $driver = crm_pdo_driver();
    } catch (Exception $e) {
        $dbError = $e->getMessage();
    }
    return array(
        'service' => 'crm-lpaezsis',
        'php' => PHP_VERSION,
        'compat' => '7.4',
        'db' => $okDb ? 'ok' : 'error',
        'driver' => $driver,
        'db_error' => $okDb ? null : $dbError,
    );
});
