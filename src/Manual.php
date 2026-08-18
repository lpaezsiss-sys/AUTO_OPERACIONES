<?php

declare(strict_types=1);

namespace Crm;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Manual de usuario: Markdown → HTML / PDF (Dompdf, PHP 7.4).
 */
final class Manual
{
    /**
     * @return string
     */
    public static function rutaMarkdown()
    {
        return dirname(__DIR__) . '/MANUAL_USUARIO.md';
    }

    /**
     * @param bool $paraPdf
     * @return string
     */
    public static function html($paraPdf = false)
    {
        $path = self::rutaMarkdown();
        if (!is_file($path)) {
            Http::fail('No se encontró MANUAL_USUARIO.md', 500);
        }
        $md = file_get_contents($path);
        if (!is_string($md) || $md === '') {
            Http::fail('El manual está vacío', 500);
        }
        return self::markdownAHtml($md, (bool) $paraPdf);
    }

    /**
     * @return string PDF binario
     */
    public static function generarPdf()
    {
        self::cargarDompdf();
        $cuerpo = self::html(true);
        $root = dirname(__DIR__);
        $logoSrc = self::embedImagen($root . '/assets/img/logo.png');
        if ($logoSrc === '') {
            $logoSrc = self::embedImagen($root . '/assets/img/logo.svg');
        }
        $h = static function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };
        ob_start();
        include $root . '/templates/pdf/manual.php';
        $html = ob_get_clean();
        if (!is_string($html) || $html === '') {
            Http::fail('No se pudo componer el HTML del manual', 500);
        }

        $options = new Options();
        $options->setChroot($root);
        $options->setIsRemoteEnabled(false);
        $options->setIsHtml5ParserEnabled(true);
        $options->setDefaultFont('DejaVu Sans');
        $options->setDpi(96);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();
        return (string) $dompdf->output();
    }

    /**
     * @param string $md
     * @param bool $paraPdf
     * @return string
     */
    public static function markdownAHtml($md, $paraPdf = false)
    {
        $md = str_replace(array("\r\n", "\r"), "\n", (string) $md);
        $lines = explode("\n", $md);
        $html = array();
        $inCode = false;
        $codeLang = '';
        $codeBuf = array();
        $inUl = false;
        $inOl = false;
        $inTable = false;
        $para = array();

        $flushPara = static function () use (&$para, &$html) {
            if (count($para) === 0) {
                return;
            }
            $html[] = '<p>' . self::inline(implode(' ', $para)) . '</p>';
            $para = array();
        };
        $closeLists = static function () use (&$inUl, &$inOl, &$html) {
            if ($inUl) {
                $html[] = '</ul>';
                $inUl = false;
            }
            if ($inOl) {
                $html[] = '</ol>';
                $inOl = false;
            }
        };
        $closeTable = static function () use (&$inTable, &$html) {
            if ($inTable) {
                $html[] = '</tbody></table>';
                $inTable = false;
            }
        };

        foreach ($lines as $raw) {
            $line = $raw;
            if ($inCode) {
                if (preg_match('/^```/', $line)) {
                    $block = implode("\n", $codeBuf);
                    if ($codeLang === 'mermaid') {
                        $html[] = $paraPdf
                            ? self::flujoHtmlEstatico()
                            : '<div class="mermaid">' . htmlspecialchars($block, ENT_QUOTES, 'UTF-8') . '</div>';
                    } else {
                        $html[] = '<pre><code>' . htmlspecialchars($block, ENT_QUOTES, 'UTF-8') . '</code></pre>';
                    }
                    $inCode = false;
                    $codeLang = '';
                    $codeBuf = array();
                } else {
                    $codeBuf[] = $line;
                }
                continue;
            }
            if (preg_match('/^```(\w*)\s*$/', $line, $m)) {
                $flushPara();
                $closeLists();
                $closeTable();
                $inCode = true;
                $codeLang = strtolower((string) $m[1]);
                $codeBuf = array();
                continue;
            }
            if (trim($line) === '') {
                $flushPara();
                $closeLists();
                $closeTable();
                continue;
            }
            if (preg_match('/^---+\s*$/', $line)) {
                $flushPara();
                $closeLists();
                $closeTable();
                $html[] = '<hr>';
                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
                $flushPara();
                $closeLists();
                $closeTable();
                $lvl = strlen($m[1]);
                $html[] = '<h' . $lvl . '>' . self::inline($m[2]) . '</h' . $lvl . '>';
                continue;
            }
            if (preg_match('/^\|(.+)\|\s*$/', $line, $m)) {
                $flushPara();
                $closeLists();
                $cells = array_map('trim', explode('|', trim($line, "| \t")));
                $isSep = true;
                foreach ($cells as $c) {
                    if (!preg_match('/^:?-+:?$/', $c)) {
                        $isSep = false;
                        break;
                    }
                }
                if ($isSep) {
                    continue;
                }
                if (!$inTable) {
                    $html[] = '<table class="manual-table"><thead><tr>';
                    foreach ($cells as $c) {
                        $html[] = '<th>' . self::inline($c) . '</th>';
                    }
                    $html[] = '</tr></thead><tbody>';
                    $inTable = true;
                } else {
                    $html[] = '<tr>';
                    foreach ($cells as $c) {
                        $html[] = '<td>' . self::inline($c) . '</td>';
                    }
                    $html[] = '</tr>';
                }
                continue;
            }
            $closeTable();
            if (preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
                $flushPara();
                if ($inUl) {
                    $html[] = '</ul>';
                    $inUl = false;
                }
                if (!$inOl) {
                    $html[] = '<ol>';
                    $inOl = true;
                }
                $html[] = '<li>' . self::inline($m[1]) . '</li>';
                continue;
            }
            if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
                $flushPara();
                if ($inOl) {
                    $html[] = '</ol>';
                    $inOl = false;
                }
                if (!$inUl) {
                    $html[] = '<ul>';
                    $inUl = true;
                }
                $html[] = '<li>' . self::inline($m[1]) . '</li>';
                continue;
            }
            $closeLists();
            $para[] = trim($line);
        }
        if ($inCode) {
            $html[] = '<pre><code>' . htmlspecialchars(implode("\n", $codeBuf), ENT_QUOTES, 'UTF-8') . '</code></pre>';
        }
        $flushPara();
        $closeLists();
        $closeTable();

        $out = implode("\n", $html);
        if ($paraPdf) {
            $out = self::resolverImagenesPdf($out);
        }
        return $out;
    }

    /**
     * @param string $text
     * @return string
     */
    private static function inline($text)
    {
        $text = (string) $text;
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace(
            '/!\[([^\]]*)\]\(([^)]+)\)/',
            '<img src="$2" alt="$1" class="manual-img">',
            $text
        );
        $text = preg_replace(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            '<a href="$2">$1</a>',
            $text
        );
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        return is_string($text) ? $text : '';
    }

    /**
     * @param string $html
     * @return string
     */
    private static function resolverImagenesPdf($html)
    {
        $root = dirname(__DIR__);
        return (string) preg_replace_callback(
            '/<img src="([^"]+)" alt="([^"]*)" class="manual-img">/',
            static function ($m) use ($root) {
                $src = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                $alt = $m[2];
                $embed = self::embedImagen($root . '/' . ltrim($src, '/'));
                if ($embed === '') {
                    return '<p class="manual-img-missing">[Imagen: ' . $alt . ']</p>';
                }
                return '<img src="' . htmlspecialchars($embed, ENT_QUOTES, 'UTF-8') . '" alt="' . $alt . '" class="manual-img">';
            },
            $html
        );
    }

    /**
     * @param string $path
     * @return string data URI o file://
     */
    public static function embedImagen($path)
    {
        $path = (string) $path;
        if ($path === '' || !is_file($path)) {
            return '';
        }
        $real = realpath($path);
        $rootReal = realpath(dirname(__DIR__));
        if ($real === false || !is_string($rootReal) || strpos($real, $rootReal) !== 0) {
            return '';
        }
        $ext = strtolower((string) pathinfo($real, PATHINFO_EXTENSION));
        if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
            $im = @imagecreatefromwebp($real);
            if ($im !== false) {
                ob_start();
                imagepng($im);
                $bin = ob_get_clean();
                imagedestroy($im);
                if (is_string($bin) && $bin !== '') {
                    return 'data:image/png;base64,' . base64_encode($bin);
                }
            }
        }
        $mime = 'image/png';
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $mime = 'image/jpeg';
        } elseif ($ext === 'svg') {
            $mime = 'image/svg+xml';
        } elseif ($ext === 'gif') {
            $mime = 'image/gif';
        }
        $bin = file_get_contents($real);
        if (!is_string($bin) || $bin === '') {
            return '';
        }
        return 'data:' . $mime . ';base64,' . base64_encode($bin);
    }

    /**
     * Diagrama equivalente al Mermaid (Dompdf no ejecuta JS).
     *
     * @return string
     */
    public static function flujoHtmlEstatico()
    {
        $pasos = array(
            '1. Selección de cliente',
            '2. Carga de lista e historial',
            '3a. Catálogo  ·  3b. Ítem a pedido',
            '4. Descripciones e imágenes',
            '5. Guardar y generar PDF',
            '6. Estadísticas a pedido',
            '7. Convertir en producto (stock 0)',
        );
        $out = array('<div class="flow">');
        $n = count($pasos);
        for ($i = 0; $i < $n; $i++) {
            $out[] = '<div class="flow-step">' . htmlspecialchars($pasos[$i], ENT_QUOTES, 'UTF-8') . '</div>';
            if ($i < $n - 1) {
                $out[] = '<div class="flow-arrow">&#8595;</div>';
            }
        }
        $out[] = '</div>';
        return implode("\n", $out);
    }

    /**
     * @return void
     */
    private static function cargarDompdf()
    {
        $autoload = dirname(__DIR__) . '/lib/pdf/deps/autoload.php';
        if (!is_file($autoload)) {
            throw new \RuntimeException('Dompdf no está instalado en lib/pdf/deps.');
        }
        require_once $autoload;
    }
}
