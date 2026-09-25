<?php

declare(strict_types=1);

namespace Crm\Comex;

use Crm\Database\Connection;
use Crm\Inventory\SqliteConnector;
use Crm\Platform;
use Crm\Storage\Uploads;

final class Health
{
    /** @return array<string, mixed> */
    public static function payload(): array
    {
        $platform = Platform::inspect();
        $debug = function_exists('crm_debug') && crm_debug();

        $appDb = 'skipped';
        $appDriver = null;
        $appError = null;
        $driverName = strtolower((string) crm_env('COMEX_DB_DRIVER', 'mysql'));
        if ($driverName === 'sqlite' || (string) crm_env('DB_NAME', '') !== '') {
            try {
                Connection::app()->query('SELECT 1');
                $appDb = 'ok';
                $appDriver = Connection::driver();
            } catch (\Throwable $e) {
                $appDb = 'error';
                $appError = $debug ? $e->getMessage() : 'unavailable';
            }
        }

        $uploads = Uploads::status();
        $inventory = SqliteConnector::status($debug);

        return [
            'service' => 'comex-lpaezsis',
            'ecosystem' => [
                'crm' => 'https://crm.lpaezsis.cl',
                'inventario' => 'https://inventario.lpaezsis.cl',
                'comex' => (string) crm_env('APP_URL', 'https://comex.lpaezsis.cl'),
            ],
            'php' => $platform['php'],
            'compat' => $platform['compat'],
            'php_ok' => $platform['php_ok'],
            'php_is_81' => $platform['php_is_81'],
            'extensions' => $platform['extensions'],
            'missing_extensions' => $platform['missing'],
            'db' => $appDb,
            'driver' => $appDriver,
            'db_error' => $appError,
            'inventory' => $inventory,
            'uploads' => $uploads,
        ];
    }
}
