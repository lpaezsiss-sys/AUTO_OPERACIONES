<?php

declare(strict_types=1);

namespace Crm;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Cotización PDF corporativa (Dompdf 3.x / PHP 7.4, plantilla HTML con tablas).
 */
final class CotizacionPdf
{
    /**
     * @param array $cotizacion Resultado de Cotizaciones::show()['cotizacion']
     * @return string
     */
    public static function generar(array $cotizacion)
    {
        self::cargarDompdf();
        $html = self::html($cotizacion);
        $root = dirname(__DIR__);
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
     * HTML de la plantilla (útil para tests locales: el PDF va comprimido).
     *
     * @param array $cotizacion
     * @return string
     */
    public static function html(array $cotizacion)
    {
        $root = dirname(__DIR__);
        $emisora = isset($cotizacion['emisora']) && is_array($cotizacion['emisora'])
            ? $cotizacion['emisora']
            : ConfiguracionEmpresa::obtener();
        $vendedor = isset($cotizacion['vendedor']) && is_array($cotizacion['vendedor'])
            ? $cotizacion['vendedor']
            : null;
        $items = isset($cotizacion['items']) && is_array($cotizacion['items'])
            ? $cotizacion['items']
            : array();
        $cotizacion['numero'] = isset($cotizacion['folio']) ? (string) $cotizacion['folio'] : '';

        $logoSrc = self::rutaImagen(isset($emisora['logo_path']) ? (string) $emisora['logo_path'] : '');
        if ($logoSrc === '') {
            $logoSrc = self::rutaImagen($root . '/assets/img/logo.svg');
        }

        $marcas = self::marcasRepresentadas($root);
        $h = static function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };

        ob_start();
        include $root . '/templates/pdf/cotizacion.php';
        $html = ob_get_clean();
        return is_string($html) ? $html : '';
    }

    /**
     * @param string $root
     * @return array
     */
    public static function marcasRepresentadas($root)
    {
        $defs = array(
            array('archivo' => 'cmc.png', 'nombre' => 'CMC Alemania'),
            array('archivo' => 'paxton.png', 'nombre' => 'Paxton'),
            array('archivo' => 'sonic.png', 'nombre' => 'Sonic'),
            array('archivo' => 'aircontrol.png', 'nombre' => 'AirControl'),
            array('archivo' => 'packline.png', 'nombre' => 'PackLine'),
        );
        $out = array();
        foreach ($defs as $def) {
            $path = $root . '/assets/img/marcas/' . $def['archivo'];
            $src = self::rutaImagen($path);
            $out[] = array(
                'nombre' => $def['nombre'],
                'src' => $src,
            );
        }
        return $out;
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

    /**
     * @param string $relOrAbs
     * @return string file://... o vacío
     */
    private static function rutaImagen($relOrAbs)
    {
        $path = (string) $relOrAbs;
        if ($path === '') {
            return '';
        }
        $root = dirname(__DIR__);
        if (!self::esAbsoluto($path)) {
            $path = $root . '/' . ltrim($path, '/');
        }
        if (!is_file($path)) {
            return '';
        }
        $real = realpath($path);
        if ($real === false) {
            return '';
        }
        $rootReal = realpath($root);
        if (is_string($rootReal) && strpos($real, $rootReal) !== 0) {
            return '';
        }
        return 'file://' . $real;
    }

    /**
     * @param string $path
     * @return bool
     */
    private static function esAbsoluto($path)
    {
        if ($path === '') {
            return false;
        }
        if ($path[0] === '/') {
            return true;
        }
        return strlen($path) > 1 && $path[1] === ':';
    }
}
