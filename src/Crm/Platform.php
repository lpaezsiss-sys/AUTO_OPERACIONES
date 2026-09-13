<?php

declare(strict_types=1);

namespace Crm;

/**
 * Chequeo de runtime BlueHosting: PHP 8.1.x + extensiones CageFS.
 */
final class Platform
{
    public const PHP_MIN = '8.1.0';
    public const PHP_COMPAT = '8.1';

    /** @var list<string> */
    public const REQUIRED_EXTENSIONS = [
        'pdo_sqlite',
        'sqlite3',
        'pdo_mysql',
        'mbstring',
        'gd',
        'zip',
        'fileinfo',
        'curl',
        'json',
        'pdo',
    ];

    /**
     * @return array{
     *   php: string,
     *   compat: string,
     *   php_ok: bool,
     *   php_is_81: bool,
     *   extensions: array<string, bool>,
     *   missing: list<string>,
     *   ok: bool
     * }
     */
    public static function inspect(): array
    {
        $phpOk = version_compare(PHP_VERSION, self::PHP_MIN, '>=');
        $is81 = PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 1;

        $extensions = [];
        $missing = [];
        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            $loaded = extension_loaded($ext);
            $extensions[$ext] = $loaded;
            if (!$loaded) {
                $missing[] = $ext;
            }
        }

        return [
            'php' => PHP_VERSION,
            'compat' => self::PHP_COMPAT,
            'php_ok' => $phpOk,
            'php_is_81' => $is81,
            'extensions' => $extensions,
            'missing' => $missing,
            'ok' => $phpOk && $missing === [],
        ];
    }

    public static function assertReady(): void
    {
        $info = self::inspect();
        if (!$info['php_ok']) {
            throw new \RuntimeException(
                'Se requiere PHP ' . self::PHP_MIN . '+ (BlueHosting MultiPHP 8.1.x). Actual: ' . PHP_VERSION
            );
        }
        if ($info['missing'] !== []) {
            throw new \RuntimeException(
                'Faltan extensiones PHP: ' . implode(', ', $info['missing'])
            );
        }
    }
}
