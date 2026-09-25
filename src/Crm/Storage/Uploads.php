<?php

declare(strict_types=1);

namespace Crm\Storage;

use Crm\Env;

/**
 * Directorio `uploads/` para adjuntos COMEX.
 * Permisos objetivo: directorios 0775 (o 0755), sin PHP ejecutable (ver .htaccess).
 * Excluido de WebDAV 2078 (ver .webdavignore y deploy/webdav.exclude).
 */
final class Uploads
{
    public const DIR_MODE = 0775;
    public const FILE_MODE = 0644;

    public static function path(?string $subdir = null): string
    {
        $root = Env::getInstance()->root() . '/uploads';
        if ($subdir === null || $subdir === '') {
            return $root;
        }
        $safe = str_replace(['..', '\\'], '', $subdir);
        return $root . '/' . ltrim($safe, '/');
    }

    /** @var list<string> */
    public const SUBDIRS = [
        'comex',
        'comex/productos',
        'comex/items',
        'comex/pdf',
        'comex/xlsx',
    ];

    public static function ensure(): string
    {
        $dir = self::path();
        if (!is_dir($dir) && !mkdir($dir, self::DIR_MODE, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear uploads/.');
        }
        self::chmodDir($dir);
        foreach (self::SUBDIRS as $sub) {
            $path = self::path($sub);
            if (!is_dir($path) && !mkdir($path, self::DIR_MODE, true) && !is_dir($path)) {
                throw new \RuntimeException('No se pudo crear uploads/' . $sub . '/');
            }
            self::chmodDir($path);
        }
        return $dir;
    }

    /**
     * @return array{
     *   path: string,
     *   exists: bool,
     *   writable: bool,
     *   perm: string,
     *   webdav_excluded: bool
     * }
     */
    public static function status(): array
    {
        $dir = self::path();
        $exists = is_dir($dir);
        $perm = $exists ? substr(sprintf('%o', (int) fileperms($dir)), -4) : '';
        $marker = $dir . '/.webdav-exclude';
        return [
            'path' => 'uploads/',
            'exists' => $exists,
            'writable' => $exists && is_writable($dir),
            'perm' => $perm,
            'webdav_excluded' => is_file($marker),
        ];
    }

    public static function isSafeMode(int $mode): bool
    {
        $masked = $mode & 0777;
        return $masked === 0755 || $masked === 0775;
    }

    public static function chmodDir(string $dir): void
    {
        $current = (int) fileperms($dir) & 0777;
        if (!self::isSafeMode($current)) {
            @chmod($dir, self::DIR_MODE);
        }
    }
}
