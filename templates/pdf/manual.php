<?php

declare(strict_types=1);

/**
 * @var string $cuerpo
 * @var string $logoSrc
 * @var callable $h
 */
$h = isset($h) && is_callable($h) ? $h : static function ($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};
$cuerpo = isset($cuerpo) ? (string) $cuerpo : '';
$logoSrc = isset($logoSrc) ? (string) $logoSrc : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Manual de usuario — CRM LPAEZsis</title>
<style>
@page { margin: 22mm 16mm 22mm 16mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #0e1a24; line-height: 1.45; }
.cover { background: #05294B; color: #fff; padding: 18px 20px 16px; margin: -10px -10px 18px; }
.cover h1 { font-size: 18pt; margin: 0 0 4px; color: #fec001; }
.cover p { margin: 0; font-size: 9pt; color: #d7e4f0; }
.logo { max-height: 36px; max-width: 110px; }
h1 { font-size: 16pt; color: #05294B; page-break-before: always; }
h1:first-of-type { page-break-before: auto; }
h2 { font-size: 13pt; color: #05294B; border-bottom: 2px solid #fec001; padding-bottom: 3px; margin-top: 16px; }
h3 { font-size: 11pt; color: #0a3a66; margin-top: 12px; }
p { margin: 6px 0; }
code { font-size: 8.5pt; background: #f4f6f8; padding: 1px 4px; }
pre { background: #f4f6f8; padding: 8px; font-size: 8pt; }
ul, ol { margin: 6px 0 6px 18px; }
hr { border: 0; border-top: 1px solid #cfd8e3; margin: 14px 0; }
.manual-table { width: 100%; border-collapse: collapse; margin: 8px 0 12px; font-size: 8.5pt; }
.manual-table th { background: #05294B; color: #fff; text-align: left; padding: 5px 6px; }
.manual-table td { border: 1px solid #d5dee8; padding: 5px 6px; vertical-align: top; }
.manual-table tr:nth-child(even) td { background: #f7f9fb; }
.manual-img { max-width: 100%; max-height: 280px; border: 1px solid #d5dee8; margin: 8px 0 12px; }
.flow { margin: 10px 0 16px; text-align: center; }
.flow-step {
  display: block; background: #05294B; color: #fff; padding: 7px 10px; margin: 0 auto;
  width: 78%; font-size: 9.5pt; font-weight: bold;
}
.flow-arrow { color: #fec001; font-size: 14pt; line-height: 1.2; }
.foot { font-size: 8pt; color: #5b6b7a; margin-top: 18px; border-top: 1px solid #cfd8e3; padding-top: 6px; }
</style>
</head>
<body>
<div class="cover">
  <?php if ($logoSrc !== '') { ?>
  <img class="logo" src="<?php echo $h($logoSrc); ?>" alt="LPAEZsis">
  <?php } ?>
  <h1>Manual de usuario</h1>
  <p>CRM Industrial Omnicanal B2B · crm.lpaezsis.cl · <?php echo $h(date('d/m/Y')); ?></p>
</div>
<div class="manual-body">
<?php echo $cuerpo; ?>
</div>
<p class="foot">Documento interno LPAEZsis. Inventario en solo lectura salvo alta de SKU a pedido con stock 0.</p>
</body>
</html>
