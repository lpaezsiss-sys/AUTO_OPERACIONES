<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
crm_layout_start($id ? 'Cotización' : 'Nueva cotización', 'cotizaciones', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="cotizaciones.php" class="text-decoration-none">← Cotizaciones</a>
    <div class="d-flex gap-2 align-items-center">
        <h1 class="page-title h4 mb-0" id="title"><?php echo $id ? 'Editar cotización' : 'Nueva cotización'; ?></h1>
        <?php if ($id) { ?>
        <a class="btn btn-sm btn-outline-primary" href="api/cotizacion_pdf.php?id=<?php echo (int) $id; ?>" target="_blank">PDF</a>
        <?php } ?>
    </div>
</div>
<form id="formCot" class="card card-soft p-4">
    <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
    <div class="row g-2">
        <div class="col-md-6"><label class="form-label">Empresa</label><select class="form-select" name="empresa_id" id="selEmpresa" required></select></div>
        <div class="col-md-3"><label class="form-label">Vendedor</label><select class="form-select" name="vendedor_id" id="selVendedor"></select></div>
        <div class="col-md-3"><label class="form-label">Estado</label>
            <select class="form-select" name="estado">
                <option value="borrador">Borrador</option>
                <option value="enviada">Enviada</option>
                <option value="aceptada">Aceptada</option>
                <option value="rechazada">Rechazada</option>
                <option value="vencida">Vencida</option>
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">Validez</label><input class="form-control" type="date" name="fecha_validez"></div>
        <div class="col-md-4"><label class="form-label">Descuento global CLP</label><input class="form-control" type="number" min="0" name="descuento" value="0"></div>
        <div class="col-md-8"><label class="form-label">Notas</label><input class="form-control" name="notas"></div>
    </div>
    <hr>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">Ítems (producto o servicio)</h2>
        <div class="d-flex gap-2">
            <input id="prodQ" class="form-control form-control-sm" placeholder="Buscar SKU">
            <button class="btn btn-sm btn-outline-primary" type="button" id="btnAddProd">Agregar producto</button>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="btnAddServ">Agregar servicio</button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table" id="items">
            <thead><tr><th>Tipo</th><th>Código</th><th>Descripción</th><th>Stock</th><th>Cant.</th><th>Precio</th><th>% Desc.</th><th>Subtotal</th><th></th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
    <div class="text-end" id="totales"></div>
    <button class="btn mt-3" style="background:#fec001;color:#05294B;font-weight:700" type="submit">Guardar cotización</button>
</form>
<script>
var cotId = <?php echo (int) $id; ?>;
var items = [];
var productos = [];
function lineSub(it) {
  return Math.round(Number(it.cantidad||0) * Number(it.precio_unitario||0) * (1 - Number(it.descuento_pct||0)/100));
}
function renderItems() {
  var tb = document.querySelector("#items tbody");
  tb.innerHTML = items.map(function (it, i) {
    var warn = it.tipo_item !== "servicio" && it.stock_actual != null && Number(it.stock_actual) < Number(it.cantidad);
    var serv = it.tipo_item === "servicio";
    return '<tr><td>'+(serv?'Servicio':'Producto')+'</td><td>'+(serv?'<input data-i="'+i+'" data-k="codigo" class="form-control form-control-sm" value="'+it.codigo+'">':it.codigo)+'</td><td>'+(serv?'<input data-i="'+i+'" data-k="descripcion" class="form-control form-control-sm" value="'+it.descripcion+'">':it.descripcion)+'</td><td><span class="badge badge-stock '+(warn?'low':'')+'">'+(serv||it.stock_actual==null?'—':it.stock_actual)+'</span></td><td><input data-i="'+i+'" data-k="cantidad" class="form-control form-control-sm" type="number" min="1" value="'+it.cantidad+'"></td><td><input data-i="'+i+'" data-k="precio_unitario" class="form-control form-control-sm" type="number" min="0" value="'+it.precio_unitario+'"></td><td><input data-i="'+i+'" data-k="descuento_pct" class="form-control form-control-sm" type="number" min="0" max="100" value="'+it.descuento_pct+'"></td><td class="text-end line-sub">'+crmClp(lineSub(it))+'</td><td><button type="button" class="btn btn-sm btn-outline-danger" data-del="'+i+'">x</button></td></tr>';
  }).join("");
  updateTotalesCot();
}
function updateTotalesCot() {
  var sub = items.reduce(function (s, it) { return s + lineSub(it); }, 0);
  var desc = Number(document.querySelector('[name=descuento]').value || 0);
  var neto = Math.max(0, sub - desc);
  var iva = Math.round(neto * 0.19);
  document.getElementById("totales").innerHTML = '<div>Subtotal '+crmClp(sub)+'</div><div>IVA 19% '+crmClp(iva)+'</div><div class="fw-bold">Total '+crmClp(neto+iva)+'</div>';
}
function addProducto(p) {
  items.push({ tipo_item: "producto", producto_id: p.id, codigo: p.codigo, descripcion: p.nombre, cantidad: 1, precio_unitario: p.precio_unitario, descuento_pct: 0, stock_actual: p.stock });
  renderItems();
}
Promise.all([crmApi("api/empresas.php"), crmApi("api/productos.php"), crmApi("api/vendedores.php")]).then(function (arr) {
  document.getElementById("selEmpresa").innerHTML = (arr[0].empresas||[]).map(function (e) {
    return '<option value="'+e.id+'">'+e.razon_social+'</option>';
  }).join("");
  document.getElementById("selVendedor").innerHTML = '<option value="">(según usuario)</option>' +
    (arr[2].vendedores||[]).filter(function (v) { return Number(v.activo) === 1; }).map(function (v) {
      return '<option value="'+v.id+'">'+v.nombre_completo+' · '+Number(v.comision_porcentaje).toFixed(2)+'%</option>';
    }).join("");
  productos = arr[1].productos || [];
  if (cotId) {
    return crmApi("api/cotizaciones.php?id="+cotId);
  }
  return null;
}).then(function (d) {
  if (!d) return;
  var c = d.cotizacion;
  document.getElementById("title").textContent = c.folio;
  document.querySelector('[name=empresa_id]').value = c.empresa_id;
  document.querySelector('[name=vendedor_id]').value = c.vendedor_id || "";
  document.querySelector('[name=estado]').value = c.estado;
  document.querySelector('[name=fecha_validez]').value = c.fecha_validez || "";
  document.querySelector('[name=descuento]').value = c.descuento || 0;
  document.querySelector('[name=notas]').value = c.notas || "";
  items = (c.items||[]).map(function (it) {
    return { tipo_item: it.tipo_item || "producto", producto_id: it.producto_id, codigo: it.codigo, descripcion: it.descripcion, cantidad: it.cantidad, precio_unitario: it.precio_unitario, descuento_pct: it.descuento_pct, stock_actual: it.stock_actual };
  });
  renderItems();
});
document.querySelector('[name=descuento]').addEventListener("input", updateTotalesCot);
document.getElementById("btnAddServ").addEventListener("click", function () {
  items.push({ tipo_item: "servicio", producto_id: null, codigo: "SERV", descripcion: "", cantidad: 1, precio_unitario: 0, descuento_pct: 0, stock_actual: null });
  renderItems();
});
document.getElementById("btnAddProd").addEventListener("click", function () {
  var q = document.getElementById("prodQ").value.toLowerCase();
  var p = productos.filter(function (x) { return String(x.codigo).toLowerCase()===q || String(x.nombre).toLowerCase().indexOf(q)>=0; })[0];
  if (!p) { crmToast("Producto no encontrado en inventario", true); return; }
  addProducto(p);
});
document.querySelector("#items tbody").addEventListener("input", function (ev) {
  var i = ev.target.getAttribute("data-i");
  var k = ev.target.getAttribute("data-k");
  if (i == null) return;
  items[i][k] = ev.target.value;
  var row = ev.target.closest("tr");
  if (row) {
    var subCell = row.querySelector(".line-sub");
    if (subCell) subCell.textContent = crmClp(lineSub(items[i]));
  }
  updateTotalesCot();
});
document.querySelector("#items tbody").addEventListener("click", function (ev) {
  var del = ev.target.getAttribute("data-del");
  if (del == null) return;
  items.splice(Number(del), 1);
  renderItems();
});
document.getElementById("formCot").addEventListener("submit", function (ev) {
  ev.preventDefault();
  var body = crmForm("formCot");
  body.items = items;
  body.descuento = Number(body.descuento || 0);
  var method = cotId ? "PUT" : "POST";
  var url = cotId ? "api/cotizaciones.php?id="+cotId : "api/cotizaciones.php";
  crmApi(url, { method: method, body: body })
    .then(function (d) { crmToast("Cotización "+d.cotizacion.folio+" guardada"); window.location.href = "cotizacion.php?id="+d.cotizacion.id; })
    .catch(function (e) { crmToast(e.message, true); });
});
renderItems();
</script>
<?php crm_layout_end(); ?>
