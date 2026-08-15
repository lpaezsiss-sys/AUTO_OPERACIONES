<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Cotizaciones', 'cotizaciones', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="page-title h3 mb-0">Cotizaciones</h1>
    <a class="btn" style="background:#fec001;color:#05294B;font-weight:700" href="cotizador.php">Nueva cotización</a>
</div>
<div class="card card-soft p-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Folio</th><th>Empresa</th><th>Estado</th><th>Emisión</th><th class="text-end">Total</th><th></th></tr></thead>
            <tbody id="rows"></tbody>
        </table>
    </div>
</div>
<script>
crmApi("api/cotizaciones.php").then(function (d) {
  document.getElementById("rows").innerHTML = (d.cotizaciones||[]).map(function (c) {
    return '<tr><td><a href="cotizacion.php?id='+c.id+'">'+c.folio+'</a></td><td>'+c.razon_social+'</td><td>'+c.estado+'</td><td>'+c.fecha_emision+'</td><td class="text-end">'+crmClp(c.total)+'</td><td><a class="btn btn-sm btn-outline-primary" href="api/cotizacion_pdf.php?id='+c.id+'" target="_blank">PDF</a></td></tr>';
  }).join("");
}).catch(function (e) { crmToast(e.message, true); });
</script>
<?php crm_layout_end(); ?>
