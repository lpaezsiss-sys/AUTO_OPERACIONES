<?php

declare(strict_types=1);

namespace Crm\Comex;

use Crm\ApiException;
use Crm\Database\Connection;
use Crm\Storage\Uploads;
use PDO;

/**
 * Repositorio documental por operación (equivalente a operations/[id]/documents).
 * Trazabilidad: usuario + fecha de carga.
 */
final class Documentos
{
    public const DIR = 'comex/docs';
    public const MAX_BYTES = 8388608;

    /** @var array<string, string> */
    public const TIPOS = [
        'FACTURA_COMERCIAL' => 'Factura Comercial',
        'PACKING_LIST' => 'Packing List',
        'BL_AWB' => 'BL / AWB',
        'CERTIFICADO' => 'Certificados',
        'DIN_DUS' => 'DIN / DUS',
    ];

    /** @var array<string, string> */
    public const MIME_EXT = [
        'application/pdf' => 'pdf',
        'application/x-pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function paraOperacion(int $operacionId): array
    {
        $op = Operaciones::porId($operacionId);
        if ($op === null) {
            throw new ApiException('Operación no encontrada', 404);
        }
        $docs = self::listar($operacionId);
        $tipoOp = strtoupper((string) ($op['tipo'] ?? ''));
        $tipos = self::TIPOS;
        if ($tipoOp === Operaciones::IMPORTACION) {
            $tipos['DIN_DUS'] = 'DIN';
        } elseif ($tipoOp === Operaciones::EXPORTACION) {
            $tipos['DIN_DUS'] = 'DUS';
        }
        $porTipo = [];
        foreach ($tipos as $codigo => $nombre) {
            $porTipo[$codigo] = [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'cantidad' => 0,
            ];
        }
        foreach ($docs as $doc) {
            $cod = (string) ($doc['tipo'] ?? '');
            if (isset($porTipo[$cod])) {
                $porTipo[$cod]['cantidad']++;
            }
        }
        return [
            'operacion' => $op,
            'tipos' => $tipos,
            'por_tipo' => array_values($porTipo),
            'documentos' => $docs,
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function listar(int $operacionId): array
    {
        $stmt = Connection::app()->prepare(
            'SELECT * FROM comex_documentos WHERE operacion_id = ? ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$operacionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row)) {
                $out[] = self::hidratar($row);
            }
        }
        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function porId(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = Connection::app()->prepare('SELECT * FROM comex_documentos WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? self::hidratar($row) : null;
    }

    /**
     * @param array<string, mixed> $file  Entrada estilo $_FILES['archivo']
     * @return array<string, mixed>
     */
    public static function subir(int $operacionId, array $file, string $tipo, string $usuario = 'COMEX'): array
    {
        $op = Operaciones::porId($operacionId);
        if ($op === null) {
            throw new ApiException('Operación no encontrada', 404);
        }
        $tipo = strtoupper(trim($tipo));
        if (!isset(self::TIPOS[$tipo])) {
            throw new ApiException('tipo debe ser Factura Comercial, Packing List, BL/AWB, Certificados o DIN/DUS', 400);
        }
        $usuario = trim($usuario);
        if ($usuario === '') {
            $usuario = 'COMEX';
        }
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            throw new ApiException('Debe adjuntar un PDF o imagen (JPG/PNG/WEBP)', 400);
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            throw new ApiException('Archivo temporal no disponible', 400);
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            $size = (int) filesize($tmp);
        }
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new ApiException('El archivo no debe superar 8 MB', 400);
        }
        $nombreOrig = self::nombreSeguro((string) ($file['name'] ?? 'documento'));
        $extName = strtolower((string) pathinfo($nombreOrig, PATHINFO_EXTENSION));
        if (in_array($extName, ['php', 'phtml', 'php5', 'phar', 'cgi', 'exe', 'sh'], true)) {
            throw new ApiException('Extensión no permitida', 400);
        }
        $mime = self::detectarMime($tmp, (string) ($file['type'] ?? ''));
        $ext = self::MIME_EXT[$mime] ?? '';
        if ($ext === '') {
            throw new ApiException('Solo se aceptan PDF, JPG, PNG o WEBP', 400);
        }
        Uploads::ensure();
        $relDir = self::DIR . '/' . $operacionId;
        $absDir = Uploads::path($relDir);
        if (!is_dir($absDir) && !mkdir($absDir, Uploads::DIR_MODE, true) && !is_dir($absDir)) {
            throw new ApiException('No se pudo crear uploads/comex/docs', 500);
        }
        Uploads::chmodDir($absDir);
        $fname = $tipo . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $rel = $relDir . '/' . $fname;
        $abs = Uploads::path($rel);
        $raw = file_get_contents($tmp);
        if (!is_string($raw) || file_put_contents($abs, $raw) === false) {
            throw new ApiException('No se pudo guardar en uploads/comex/docs (permisos 755/775)', 500);
        }
        @chmod($abs, Uploads::FILE_MODE);
        $now = crm_now();
        $ins = Connection::app()->prepare(
            'INSERT INTO comex_documentos
                (operacion_id, tipo, nombre_original, mime, size_bytes, path, usuario, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $path = 'uploads/' . $rel;
        $ins->execute([$operacionId, $tipo, $nombreOrig, $mime, $size, $path, $usuario, $now]);
        $id = (int) Connection::app()->lastInsertId();
        $row = self::porId($id);
        if ($row === null) {
            throw new ApiException('No se pudo registrar el documento', 500);
        }
        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public static function eliminar(int $id): array
    {
        $row = self::porId($id);
        if ($row === null) {
            throw new ApiException('Documento no encontrado', 404);
        }
        $abs = self::absPath((string) $row['path']);
        if (is_file($abs)) {
            @unlink($abs);
        }
        $del = Connection::app()->prepare('DELETE FROM comex_documentos WHERE id = ?');
        $del->execute([$id]);
        return ['eliminado' => $id, 'operacion_id' => (int) $row['operacion_id']];
    }

    public static function eliminarPorOperacion(int $operacionId): int
    {
        $docs = self::listar($operacionId);
        $n = 0;
        foreach ($docs as $doc) {
            self::eliminar((int) $doc['id']);
            $n++;
        }
        $dir = Uploads::path(self::DIR . '/' . $operacionId);
        if (is_dir($dir)) {
            @rmdir($dir);
        }
        return $n;
    }

    public static function stream(int $id, bool $download = false): never
    {
        $row = self::porId($id);
        if ($row === null) {
            throw new ApiException('Documento no encontrado', 404);
        }
        $abs = self::absPath((string) $row['path']);
        $root = realpath(Uploads::path());
        $real = is_file($abs) ? realpath($abs) : false;
        if ($root === false || $real === false || !str_starts_with($real, rtrim($root, '/'))) {
            throw new ApiException('Archivo no encontrado', 404);
        }
        $mime = (string) $row['mime'];
        $name = self::nombreSeguro((string) $row['nombre_original']);
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, must-revalidate');
        $disp = $download ? 'attachment' : 'inline';
        header('Content-Disposition: ' . $disp . '; filename="' . str_replace('"', '', $name) . '"');
        header('Content-Length: ' . (string) filesize($real));
        readfile($real);
        exit;
    }

    public static function absPath(string $rel): string
    {
        $rel = preg_replace('#^uploads/#', '', $rel) ?? $rel;
        $rel = str_replace(['..', '\\'], '', $rel);
        return Uploads::path($rel);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hidratar(array $row): array
    {
        $id = (int) $row['id'];
        $mime = (string) ($row['mime'] ?? '');
        $tipo = (string) ($row['tipo'] ?? '');
        $row['id'] = $id;
        $row['operacion_id'] = (int) $row['operacion_id'];
        $row['size_bytes'] = (int) ($row['size_bytes'] ?? 0);
        $row['tipo_label'] = self::TIPOS[$tipo] ?? $tipo;
        $row['es_pdf'] = str_contains($mime, 'pdf');
        $row['es_imagen'] = str_starts_with($mime, 'image/');
        $row['preview_url'] = 'api/documentos.php?id=' . $id . '&file=1';
        $row['download_url'] = 'api/documentos.php?id=' . $id . '&download=1';
        return $row;
    }

    private static function detectarMime(string $tmp, string $fallback): string
    {
        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi !== false) {
                $detected = finfo_file($fi, $tmp);
                finfo_close($fi);
                if (is_string($detected)) {
                    $mime = strtolower($detected);
                }
            }
        }
        if ($mime === '' || $mime === 'application/octet-stream') {
            $mime = strtolower(trim($fallback));
        }
        return $mime;
    }

    private static function nombreSeguro(string $name): string
    {
        $name = str_replace(["\0", '/', '\\'], '', $name);
        $name = trim($name);
        if ($name === '') {
            return 'documento';
        }
        return substr($name, 0, 180);
    }
}
