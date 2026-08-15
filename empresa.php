<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: empresas.php');
    exit;
}
crm_layout_start('Ficha empresa', 'empresas', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="empresas.php" class="text-decoration-none">← Empresas</a>
</div>
<div id="ficha"></div>
<script>
var id = <?php echo (int) $id; ?>;
crmApi("api/empresas.php?id="+id).then(function (d) {
  var e = d.empresa;
  var html = '';
  html += '<div class="card card-soft p-4 mb-3"><h1 class="h4 page-title">'+e.razon_social+'</h1>';
  html += '<div class="text-secondary">'+e.rut+' · '+(e.industria||"")+' · '+(e.region||"")+'</div>';
  html += '<div class="mt-2">Origen: <strong>'+e.origen+'</strong> · Estado: <strong>'+e.estado+'</strong></div></div>';
  html += '<div class="row g-3">';
  html += '<div class="col-lg-4"><div class="card card-soft p-3"><h2 class="h6">Contactos</h2>'+(d.contactos||[]).map(function (c) {
    return '<div class="border-bottom py-2"><strong>'+c.nombre+' '+(c.apellido||"")+'</strong><div class="small text-secondary">'+(c.cargo||"")+' · '+c.canal_preferido+'</div></div>';
  }).join("")+'</div></div>';
  html += '<div class="col-lg-4"><div class="card card-soft p-3"><h2 class="h6">Oportunidades</h2>'+(d.oportunidades||[]).map(function (o) {
    return '<div class="border-bottom py-2"><div>'+o.codigo+' · '+o.titulo+'</div><div class="small">'+o.etapa+' · '+crmClp(o.valor_estimado)+'</div></div>';
  }).join("")+'</div></div>';
  html += '<div class="col-lg-4"><div class="card card-soft p-3"><h2 class="h6">Cotizaciones</h2>'+(d.cotizaciones||[]).map(function (c) {
    return '<div class="border-bottom py-2"><a href="cotizacion.php?id='+c.id+'">'+c.folio+'</a> · '+c.estado+'<div class="small">'+crmClp(c.total)+'</div></div>';
  }).join("")+'</div></div></div>';
  html += '<div class="card card-soft p-3 mt-3"><h2 class="h6">Línea de tiempo omnicanal</h2>'+(d.actividades||[]).map(function (a) {
    return '<div class="border-bottom py-2"><strong>'+a.titulo+'</strong> <span class="badge text-bg-light">'+a.canal+'</span><div class="small text-secondary">'+a.tipo+' · '+a.estado+'</div></div>';
  }).join("")+'</div>';
  document.getElementById("ficha").innerHTML = html;
}).catch(function (e) { crmToast(e.message, true); });
</script>
<?php crm_layout_end(); ?>
