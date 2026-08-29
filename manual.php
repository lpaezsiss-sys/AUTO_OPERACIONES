<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
$cuerpo = \Crm\Manual::html(false);
crm_layout_start('Manual de usuario', 'manual', $user);
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title h3 mb-1">Manual de usuario</h1>
        <p class="text-secondary mb-0">CRM LPAEZsis · guía operativa, arquitectura y guion para inversionistas.</p>
    </div>
    <a class="btn" style="background:#fec001;color:#05294B;font-weight:700" href="api/manual_pdf.php">Descargar Manual en PDF</a>
</div>
<article class="card card-soft p-4 manual-doc" id="manualContent">
<?php echo $cuerpo; ?>
</article>
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script>
if (window.mermaid) {
  mermaid.initialize({ startOnLoad: true, theme: "base", themeVariables: {
    primaryColor: "#05294B",
    primaryTextColor: "#ffffff",
    primaryBorderColor: "#fec001",
    lineColor: "#05294B",
    secondaryColor: "#fec001",
    tertiaryColor: "#f4f6f8"
  }});
}
</script>
<?php
crm_layout_end();
