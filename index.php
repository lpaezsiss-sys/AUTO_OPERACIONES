<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Dashboard', 'dashboard', $user);
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="page-title h3 mb-1">Dashboard comercial</h1>
        <p class="text-secondary mb-0">Pipeline B2B, cotizaciones e inventario en vivo.</p>
    </div>
</div>
<div class="row g-3" id="kpis"></div>
<div class="row g-3 mt-1">
    <div class="col-lg-7">
        <div class="card card-soft p-3">
                        h2 class="h6" style="color:#05294B">Actividades recientes</h2>
            <div id="actList" class="small"></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card card-soft p-3">
            <h2 class="h6" style="color:#05294B">Cotizaciones recientes</h2>
            <div id="cotList" class="small"></div>
        </div>
    </div>
</div>
<script>
crmApi("api/dashboard.php").then(function (d) {
  var k = d.kpis || {};
  var items = [
    ["Empresas", k.empresas],
    ["Oportunidades abiertas", k.oportunidades_abiertas],
    ["Pipeline", crmClp(k.pipeline_clp)],
    ["Cotizaciones del mes", k.cotizaciones_mes],
    ["Actividades pendientes", k.actividades_pendientes],
    ["SKU bajo stock", k.productos_bajo_stock]
  ];
  document.getElementById("kpis").innerHTML = items.map(function (it) {
    return '<div class="col-6 col-xl-4"><div class="card kpi p-3"><div class="kpi-label">'+it[0]+'</div><div class="kpi-value">'+it[1]+'</div></div></div>';
  }).join("");
  document.getElementById("actList").innerHTML = (d.actividades_recientes || []).map(function (a) {
    return '<div class="d-flex justify-content-between border-bottom py-2"><div><strong>'+a.titulo+'</strong><div class="text-secondary">'+ (a.razon_social || "Sin empresa") +' · '+a.canal+'</div></div><span class="badge text-bg-light">'+a.estado+'</span></div>';
  }).join("") || '<div class="text-secondary">Sin actividades.</div>';
  document.getElementById("cotList").innerHTML = (d.cotizaciones_recientes || []).map(function (c) {
    return '<div class="d-flex justify-content-between border-bottom py-2"><a href="cotizacion.php?id='+c.id+'">'+c.folio+'</a><span>'+crmClp(c.total)+'</span></div>';
  }).join("") || '<div class="text-secondary">Sin cotizaciones.</div>';
}).catch(function (e) { crmToast(e.message, true); });
</script>
<?php crm_layout_end(); ?>
