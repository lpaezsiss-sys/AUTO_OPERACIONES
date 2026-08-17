#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Genera assets/img/logo.png (fallback del PDF; Dompdf no pinta bien el SVG).
 * Uso local: php scripts/generar_logo_png.php
 */

$w = 256;
$h = 256;
$im = imagecreatetruecolor($w, $h);
imagesavealpha($im, true);
$trans = imagecolorallocatealpha($im, 0, 0, 0, 127);
imagefill($im, 0, 0, $trans);
$navy = imagecolorallocate($im, 5, 41, 75);
$white = imagecolorallocate($im, 255, 255, 255);
$gold = imagecolorallocate($im, 254, 192, 1);
$r = 48;
imagefilledrectangle($im, $r, 0, $w - $r - 1, $h - 1, $navy);
imagefilledrectangle($im, 0, $r, $w - 1, $h - $r - 1, $navy);
imagefilledellipse($im, $r, $r, $r * 2, $r * 2, $navy);
imagefilledellipse($im, $w - $r - 1, $r, $r * 2, $r * 2, $navy);
imagefilledellipse($im, $r, $h - $r - 1, $r * 2, $r * 2, $navy);
imagefilledellipse($im, $w - $r - 1, $h - $r - 1, $r * 2, $r * 2, $navy);
imagefilledrectangle($im, 48, 56, 84, 196, $white);
imagefilledrectangle($im, 48, 164, 140, 196, $white);
imagefilledrectangle($im, 164, 56, 212, 196, $gold);

$root = dirname(__DIR__);
$out = $root . '/assets/img/logo.png';
imagepng($im, $out);
imagedestroy($im);
echo $out . ' ' . filesize($out) . " bytes\n";
exit(0);
