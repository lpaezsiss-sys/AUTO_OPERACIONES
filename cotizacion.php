<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$folioAsignado = '';
if ($id > 0) {
    $folioStmt = crm_pdo()->prepare('SELECT folio FROM crm_cotizaciones WHERE id = ? LIMIT 1');
    $folioStmt->execute(array($id));
    $folioAsignado = (string) $folioStmt->fetchColumn();
}
$proximoFolio = $folioAsignado === '' ? \Crm\Codes::peek('crm_cotizaciones', 'folio', 'COT') : '';
crm_layout_start($folioAsignado !== '' ? $folioAsignado : 'Nueva cotización', 'cotizaciones', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="cotizaciones.php" class="text-decoration-none">← Cotizaciones</a>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <h1 class="page-title h4 mb-0" id="title"><?php echo $folioAsignado !== '' ? crm_h($folioAsignado) : 'Nueva cotización'; ?></h1>
        <?php if ($folioAsignado !== '') { ?>
        <span id="folioBadge" class="badge-folio badge-folio-ok"><?php echo crm_h($folioAsignado); ?></span>
        <?php } else { ?>
        <span id="folioBadge" class="badge-folio badge-folio-new">Cotización Nueva (Próximo Nº: <?php echo crm_h($proximoFolio); ?>)</span>
        <?php } ?>
        <?php if ($id) { ?>
        <a class="btn btn-sm btn-outline-primary" href="api/cotizacion_pdf.php?id=<?php echo (int) $id; ?>" target="_blank">PDF</a>
        <button class="btn btn-sm btn-outline-danger" type="button" id="btnEliminarCot">Eliminar</button>
        <?php } ?>
    </div>
</div>
<form id="formCot" class="card card-soft p-4">
    <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
    <div class="row g-2">
        <div class="col-md-6"><label class="form-label">Empresa</label><select class="form-select" name="empresa_id" id="selEmpresa" required></select></div>
        <div class="col-md-6"><label class="form-label">Contacto</label><select class="form-select" name="contacto_id" id="selContacto"><option value="">(sin contacto)</option></select></div>
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
        <div class="col-md-3"><label class="form-label">Fecha validez</label><input class="form-control" type="date" name="fecha_validez"></div>
        <div class="col-md-3"><label class="form-label">Moneda</label>
            <select class="form-select" name="moneda">
                <option value="CLP">CLP</option>
                <option value="USD">USD</option>
                <option value="UF">UF</option>
                <option value="EUR">EUR</option>
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">Validez de la oferta</label><input class="form-control" name="validez_oferta" placeholder="Ej: 30 días"></div>
        <div class="col-md-3"><label class="form-label">Condiciones de pago</label><input class="form-control" name="condiciones_pago"></div>
        <div class="col-md-3"><label class="form-label">Plazo de entrega</label><input class="form-control" name="plazo_entrega"></div>
        <div class="col-md-3"><label class="form-label">Lugar de entrega</label><input class="form-control" name="lugar_entrega"></div>
        <div class="col-md-3"><label class="form-label">Descuento global</label><input class="form-control" type="number" min="0" name="descuento" value="0"></div>
        <div class="col-md-6"><label class="form-label">Notas</label><input class="form-control" name="notas"></div>
        <div class="col-12">
            <label class="form-label">Marcas en el PDF</label>
            <div id="marcasBox" class="d-flex flex-wrap gap-3 border rounded p-2 bg-white"></div>
            <div class="form-text">Si no marca ninguna, el PDF usa las marcas globales activas.</div>
        </div>
    </div>
    <hr>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">Ítems (inventario, a pedido o servicio)</h2>
        <div class="d-flex gap-2">
            <input id="prodQ" class="form-control form-control-sm" placeholder="Buscar SKU">
            <button class="btn btn-sm btn-outline-primary" type="button" id="btnAddProd">Agregar producto</button>
            <button class="btn btn-sm btn-outline-warning" type="button" id="btnAddPedido">Ítem a pedido</button>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="btnAddServ">Agregar servicio</button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table" id="items">
            <thead><tr><th>Tipo</th><th>Código</th><th>Descripción</th><th>Marca</th><th>Stock</th><th>Cant.</th><th>Precio</th><th>Costo</th><th>% Desc.</th><th>Subtotal</th><th></th></tr></thead>
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
var marcasCatalogo = [];
var pendingContactoId = "";
function lineSub(it) {
  return Math.round(Number(it.cantidad||0) * Number(it.precio_unitario||0) * (1 - Number(it.descuento_pct||0)/100));
}
function tipoLabel(it) {
  if (it.tipo_item === "servicio") return "Servicio";
  if (it.tipo_item === "a_pedido") return "A pedido";
  return "Producto";
}
function esLibre(it) {
  return it.tipo_item === "servicio" || it.tipo_item === "a_pedido";
}
function marcaCell(it, i) {
  if (it.tipo_item !== "a_pedido") return "—";
  var opts = '<option value="">Otra / escribir</option>' + marcasCatalogo.map(function (m) {
    return '<option value="'+m.id+'"'+(Number(it.marca_id)===Number(m.id)?' selected':'')+'>'+m.nombre+'</option>';
  }).join("");
  return '<select class="form-select form-select-sm mb-1" data-i="'+i+'" data-k="marca_id">'+opts+'</select>' +
    '<input class="form-control form-control-sm" data-i="'+i+'" data-k="marca_nombre" placeholder="Marca" value="'+crmEsc(it.marca_nombre||"")+'">';
}
function thumbHtml(it) {
  if (!it.imagen_url) return '<span class="small text-secondary">Sin imagen</span>';
  return '<img src="'+crmEsc(it.imagen_url)+'" alt="" style="max-height:40px;max-width:56px;object-fit:contain">';
}
function extraRow(it, i) {
  var pedido = it.tipo_item === "a_pedido";
  var ph = pedido ? "URL https:// o suba un archivo" : "URL opcional (si el inventario no tiene foto)";
  return '<tr class="item-extra"><td></td><td colspan="9">'+
    '<div class="d-flex gap-2 align-items-start">'+
    '<div style="min-width:60px">'+thumbHtml(it)+'</div>'+
    '<div class="flex-grow-1">'+
    '<textarea class="form-control form-control-sm mb-1" rows="2" data-i="'+i+'" data-k="descripcion_detallada" placeholder="Descripción detallada / especificaciones (opcional)">'+crmEsc(it.descripcion_detallada||"")+'</textarea>'+
    '<input class="form-control form-control-sm mb-1" data-i="'+i+'" data-k="imagen_url" placeholder="'+ph+'" value="'+crmEsc(it.imagen_url||"")+'">'+
    '<input class="form-control form-control-sm" type="file" accept="image/png,image/jpeg" data-img="'+i+'">'+
    '</div></div></td><td></td></tr>';
}
function renderItems() {
  var tb = document.querySelector("#items tbody");
  tb.innerHTML = items.map(function (it, i) {
    var warn = !esLibre(it) && it.stock_actual != null && Number(it.stock_actual) < Number(it.cantidad);
    var libre = esLibre(it);
    var pedido = it.tipo_item === "a_pedido";
    return '<tr><td>'+tipoLabel(it)+'</td><td>'+(libre?'<input data-i="'+i+'" data-k="codigo" class="form-control form-control-sm" value="'+crmEsc(it.codigo||"")+'">':crmEsc(it.codigo||""))+'</td><td>'+(libre?'<input data-i="'+i+'" data-k="descripcion" class="form-control form-control-sm" value="'+crmEsc(it.descripcion||"")+'">':crmEsc(it.descripcion||""))+'</td><td>'+marcaCell(it,i)+'</td><td><span class="badge badge-stock '+(warn?'low':'')+'">'+(libre||it.stock_actual==null?'—':it.stock_actual)+'</span></td><td><input data-i="'+i+'" data-k="cantidad" class="form-control form-control-sm" type="number" min="1" value="'+it.cantidad+'"></td><td><input data-i="'+i+'" data-k="precio_unitario" class="form-control form-control-sm" type="number" min="0" value="'+it.precio_unitario+'"></td><td>'+(pedido?'<input data-i="'+i+'" data-k="costo_unitario" class="form-control form-control-sm" type="number" min="0" value="'+(it.costo_unitario||0)+'">':'—')+'</td><td><input data-i="'+i+'" data-k="descuento_pct" class="form-control form-control-sm" type="number" min="0" max="100" value="'+it.descuento_pct+'"></td><td class="text-end line-sub">'+crmClp(lineSub(it))+'</td><td><button type="button" class="btn btn-sm btn-outline-danger" data-del="'+i+'">x</button></td></tr>'+extraRow(it,i);
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
  items.push({
    tipo_item: "producto",
    producto_id: p.id,
    codigo: p.codigo,
    descripcion: p.nombre,
    descripcion_detallada: (p.descripcion && p.descripcion !== p.nombre) ? p.descripcion : "",
    imagen_url: p.imagen_url || "",
    cantidad: 1,
    precio_unitario: p.precio_unitario,
    descuento_pct: 0,
    stock_actual: p.stock
  });
  renderItems();
}
function renderMarcas(selectedIds) {
  selectedIds = selectedIds || [];
  document.getElementById("marcasBox").innerHTML = marcasCatalogo.map(function (m) {
    var chk = selectedIds.indexOf(Number(m.id)) >= 0;
    var img = m.existe_archivo ? '<img src="'+m.url+'" alt="" style="max-height:22px;max-width:70px" class="me-1">' : '';
    return '<label class="form-check d-flex align-items-center gap-1 me-2 mb-1">' +
      '<input class="form-check-input" type="checkbox" value="'+m.id+'" '+(chk?'checked':'')+'>' +
      img + '<span class="small">'+(m.nombre||m.archivo)+'</span></label>';
  }).join("") || '<span class="small text-secondary">No hay marcas cargadas.</span>';
}
function loadContactos(empresaId, selected) {
  var sel = document.getElementById("selContacto");
  sel.innerHTML = '<option value="">(sin contacto)</option>';
  if (!empresaId) return Promise.resolve();
  return crmApi("api/contactos.php?empresa_id="+empresaId).then(function (d) {
    sel.innerHTML = '<option value="">(sin contacto)</option>' + (d.contactos||[]).map(function (c) {
      return '<option value="'+c.id+'">'+c.nombre+' '+(c.apellido||"")+(c.email?' · '+c.email:'')+'</option>';
    }).join("");
    if (selected) sel.value = selected;
  }).catch(function (e) { crmToast(e.message, true); });
}
Promise.all([crmApi("api/empresas.php"), crmApi("api/productos.php"), crmApi("api/vendedores.php"), crmApi("api/marcas.php")]).then(function (arr) {
  document.getElementById("selEmpresa").innerHTML = (arr[0].empresas||[]).map(function (e) {
    return '<option value="'+e.id+'">'+e.razon_social+'</option>';
  }).join("");
  document.getElementById("selVendedor").innerHTML = '<option value="">(según usuario)</option>' +
    (arr[2].vendedores||[]).filter(function (v) { return Number(v.activo) === 1; }).map(function (v) {
      return '<option value="'+v.id+'">'+v.nombre_completo+' · '+Number(v.comision_porcentaje).toFixed(2)+'%</option>';
    }).join("");
  productos = arr[1].productos || [];
  marcasCatalogo = arr[3].marcas || [];
  renderMarcas([]);
  var empSel = document.getElementById("selEmpresa");
  loadContactos(empSel.value, "");
  empSel.addEventListener("change", function () { loadContactos(empSel.value, ""); });
  if (cotId) {
    return crmApi("api/cotizaciones.php?id="+cotId);
  }
  return null;
}).then(function (d) {
  if (!d) return;
  var c = d.cotizacion;
  document.getElementById("title").textContent = c.folio;
  var badge = document.getElementById("folioBadge");
  if (badge) {
    badge.textContent = c.folio;
    badge.classList.remove("badge-folio-new");
    badge.classList.add("badge-folio-ok");
  }
  document.querySelector('[name=empresa_id]').value = c.empresa_id;
  pendingContactoId = c.contacto_id || "";
  loadContactos(c.empresa_id, pendingContactoId);
  document.querySelector('[name=vendedor_id]').value = c.vendedor_id || "";
  document.querySelector('[name=estado]').value = c.estado;
  document.querySelector('[name=fecha_validez]').value = c.fecha_validez || "";
  document.querySelector('[name=moneda]').value = c.moneda || "CLP";
  document.querySelector('[name=validez_oferta]').value = c.validez_oferta || "";
  document.querySelector('[name=condiciones_pago]').value = c.condiciones_pago || "";
  document.querySelector('[name=plazo_entrega]').value = c.plazo_entrega || "";
  document.querySelector('[name=lugar_entrega]').value = c.lugar_entrega || "";
  document.querySelector('[name=descuento]').value = c.descuento || 0;
  document.querySelector('[name=notas]').value = c.notas || "";
  items = (c.items||[]).map(function (it) {
    return { tipo_item: it.tipo_item || "producto", es_a_pedido: it.es_a_pedido || 0, producto_id: it.producto_id, marca_id: it.marca_id || 0, marca_nombre: it.marca_nombre || "", codigo: it.codigo, descripcion: it.descripcion, descripcion_detallada: it.descripcion_detallada || "", imagen_url: it.imagen_url || "", cantidad: it.cantidad, precio_unitario: it.precio_unitario, costo_unitario: it.costo_unitario || 0, descuento_pct: it.descuento_pct, stock_actual: it.stock_actual };
  });
  renderItems();
  renderMarcas((c.marca_ids || []).map(Number));
});
document.querySelector('[name=descuento]').addEventListener("input", updateTotalesCot);
document.getElementById("btnAddPedido").addEventListener("click", function () {
  items.push({ tipo_item: "a_pedido", es_a_pedido: 1, producto_id: null, marca_id: 0, marca_nombre: "", codigo: "PEDIDO", descripcion: "", descripcion_detallada: "", imagen_url: "", cantidad: 1, precio_unitario: 0, costo_unitario: 0, descuento_pct: 0, stock_actual: null });
  renderItems();
});
document.getElementById("btnAddServ").addEventListener("click", function () {
  items.push({ tipo_item: "servicio", producto_id: null, codigo: "SERV", descripcion: "", descripcion_detallada: "", imagen_url: "", cantidad: 1, precio_unitario: 0, descuento_pct: 0, stock_actual: null });
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
  if (k === "marca_id") {
    items[i].marca_id = Number(ev.target.value || 0);
    var opt = ev.target.options[ev.target.selectedIndex];
    if (items[i].marca_id > 0 && opt) {
      items[i].marca_nombre = opt.text;
      var nom = row ? row.querySelector('[data-k="marca_nombre"]') : null;
      if (nom) nom.value = opt.text;
    }
  }
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
document.querySelector("#items tbody").addEventListener("change", function (ev) {
  var idx = ev.target.getAttribute("data-img");
  if (idx == null || !ev.target.files || !ev.target.files[0]) return;
  var fd = new FormData();
  fd.append("archivo", ev.target.files[0]);
  crmApi("api/cotizacion_item_imagen.php", { method: "POST", body: fd }).then(function (d) {
    items[Number(idx)].imagen_url = d.imagen_url;
    renderItems();
  }).catch(function (e) { crmToast(e.message, true); });
});
document.getElementById("formCot").addEventListener("submit", function (ev) {
  ev.preventDefault();
  var body = crmForm("formCot");
  body.items = items;
  body.descuento = Number(body.descuento || 0);
  body.marca_ids = Array.prototype.map.call(document.querySelectorAll("#marcasBox input:checked"), function (el) {
    return Number(el.value);
  });
  var method = cotId ? "PUT" : "POST";
  var url = cotId ? "api/cotizaciones.php?id="+cotId : "api/cotizaciones.php";
  crmApi(url, { method: method, body: body })
    .then(function (d) { crmToast("Cotización "+d.cotizacion.folio+" guardada"); window.location.href = "cotizacion.php?id="+d.cotizacion.id; })
    .catch(function (e) { crmToast(e.message, true); });
});
var btnDel = document.getElementById("btnEliminarCot");
if (btnDel) {
  btnDel.addEventListener("click", function () {
    if (!window.confirm("¿Eliminar esta cotización y sus ítems?")) return;
    crmApi("api/cotizaciones.php?id="+cotId, { method: "DELETE" })
      .then(function () { crmToast("Cotización eliminada"); window.location.href = "cotizaciones.php"; })
      .catch(function (e) { crmToast(e.message, true); });
  });
}
renderItems();
</script>
<?php crm_layout_end(); ?>
