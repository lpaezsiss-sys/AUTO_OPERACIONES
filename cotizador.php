<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = crm_page_user();
crm_layout_start('Cotizador', 'cotizador', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="page-title h3 mb-1">Cotizador</h1>
        <p class="text-secondary mb-0">Búsqueda en vivo sobre <code>productos</code> · IVA 19% · guardado asíncrono.</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span id="folioBadge" class="badge-folio badge-folio-new">Cotización Nueva (Próximo Nº: …)</span>
        <a class="text-decoration-none" href="cotizaciones.php">Ver cotizaciones</a>
    </div>
</div>

<div class="card card-soft p-4">
    <div class="row g-3">
        <div class="col-md-5">
            <label class="form-label">Empresa</label>
            <select class="form-select" id="empresa_id"></select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Contacto</label>
            <select class="form-select" id="contacto_id">
                <option value="">(sin contacto)</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Vendedor</label>
            <select class="form-select" id="vendedor_id"></select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Lista de precios</label>
            <select class="form-select" id="lista_precio_id">
                <option value="">(predeterminada)</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Estado</label>
            <select class="form-select" id="estado">
                <option value="borrador">Borrador</option>
                <option value="enviada">Enviada</option>
                <option value="aceptada">Aceptada</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Descuento</label>
            <input class="form-control" id="descuento" type="number" min="0" value="0">
        </div>
        <div class="col-md-2">
            <label class="form-label">Moneda</label>
            <select class="form-select" id="moneda">
                <option value="CLP" selected>CLP</option>
                <option value="USD">USD</option>
                <option value="UF">UF</option>
                <option value="EUR">EUR</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Validez de la oferta</label>
            <input class="form-control" id="validez_oferta" placeholder="Ej: 30 días">
        </div>
        <div class="col-md-3">
            <label class="form-label">Condiciones de pago</label>
            <input class="form-control" id="condiciones_pago" placeholder="Ej: 50% / 50%">
        </div>
        <div class="col-md-2">
            <label class="form-label">Plazo de entrega</label>
            <input class="form-control" id="plazo_entrega" placeholder="Ej: 15 días hábiles">
        </div>
        <div class="col-md-2">
            <label class="form-label">Lugar de entrega</label>
            <input class="form-control" id="lugar_entrega" placeholder="Ciudad / faena">
        </div>
        <div class="col-12">
            <label class="form-label">Marcas en el PDF</label>
            <div id="marcasBox" class="d-flex flex-wrap gap-3 border rounded p-2 bg-white"></div>
            <div class="form-text">Si no marca ninguna, el PDF usa las marcas globales activas.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Buscar producto (SKU o nombre)</label>
            <div class="position-relative">
                <input class="form-control" id="buscar" autocomplete="off" placeholder="Escriba para buscar en inventario…">
                <div id="sugerencias" class="list-group position-absolute w-100 shadow" style="z-index:20;display:none;max-height:260px;overflow:auto;"></div>
            </div>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-outline-primary w-100" id="btnPedido" type="button">Ítem a pedido</button>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-outline-secondary w-100" id="btnServicio" type="button">Agregar servicio</button>
        </div>
    </div>

    <div class="table-responsive mt-3">
        <table class="table align-middle" id="tablaItems">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>SKU</th>
                    <th>Descripción</th>
                    <th>Marca</th>
                    <th>Stock</th>
                    <th>Cant.</th>
                    <th>Precio</th>
                    <th>Costo</th>
                    <th>% Desc.</th>
                    <th class="text-end">Subtotal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <div class="row">
        <div class="col-md-6">
            <label class="form-label">Notas</label>
            <textarea class="form-control" id="notas" rows="2"></textarea>
        </div>
        <div class="col-md-6 text-end" id="totales">
            <div>Subtotal $0</div>
            <div>IVA 19% $0</div>
            <div class="fw-bold">Total $0</div>
        </div>
    </div>

    <button class="btn mt-3" id="btnGuardar" type="button" style="background:#fec001;color:#05294B;font-weight:700">
        Guardar cotización
    </button>
    <div class="small text-success mt-2" id="okMsg" hidden></div>
</div>

<script>
(function () {
  "use strict";
  var items = [];
  var timer = null;
  var buscar = document.getElementById("buscar");
  var sug = document.getElementById("sugerencias");
  var empresasCache = [];
  var listasCache = [];

  function defaultListaId() {
    var d = 0;
    listasCache.forEach(function (l) {
      if (Number(l.es_default) === 1 && l.estado === "activa") d = Number(l.id);
    });
    return d;
  }

  function fillListasSelect(selected) {
    var sel = document.getElementById("lista_precio_id");
    sel.innerHTML = '<option value="">(predeterminada)</option>' + listasCache.filter(function (l) {
      return l.estado === "activa";
    }).map(function (l) {
      var tag = Number(l.es_default) === 1 ? " · default" : "";
      return '<option value="' + l.id + '">' + crmEsc(l.nombre) + " (" + (Number(l.porcentaje_ajuste) > 0 ? "+" : "") + Number(l.porcentaje_ajuste).toFixed(2) + "%)" + tag + "</option>";
    }).join("");
    if (selected) sel.value = String(selected);
  }

  function aplicarListaEmpresa() {
    var empId = document.getElementById("empresa_id").value;
    var emp = null;
    empresasCache.forEach(function (e) { if (String(e.id) === String(empId)) emp = e; });
    var listaId = emp && emp.lista_precio_id ? emp.lista_precio_id : defaultListaId();
    fillListasSelect(listaId);
  }

  function lineSub(it) {
    return Math.round(Number(it.cantidad || 0) * Number(it.precio_unitario || 0) * (1 - Number(it.descuento_pct || 0) / 100));
  }

  function tipoBadge(it) {
    if (it.tipo_item === "servicio") return '<span class="badge text-bg-info">Servicio</span>';
    if (it.tipo_item === "a_pedido") return '<span class="badge text-bg-warning">A pedido</span>';
    return '<span class="badge text-bg-light">Producto</span>';
  }

  function esLibre(it) {
    return it.tipo_item === "servicio" || it.tipo_item === "a_pedido";
  }

  function marcaCell(it, i) {
    if (it.tipo_item !== "a_pedido") return "—";
    var opts = '<option value="">Otra / escribir</option>' + marcasCatalogo.map(function (m) {
      return '<option value="' + m.id + '"' + (Number(it.marca_id) === Number(m.id) ? " selected" : "") + '>' + m.nombre + '</option>';
    }).join("");
    return '<select class="form-select form-select-sm mb-1" data-i="' + i + '" data-k="marca_id">' + opts + '</select>' +
      '<input class="form-control form-control-sm" data-i="' + i + '" data-k="marca_nombre" placeholder="Marca" value="' + crmEsc(it.marca_nombre || "") + '">';
  }

  function thumbHtml(it) {
    if (!it.imagen_url) {
      return '<span class="small text-secondary">Sin imagen</span>';
    }
    return '<img src="' + crmEsc(it.imagen_url) + '" alt="" style="max-height:40px;max-width:56px;object-fit:contain">';
  }

  function extraRow(it, i) {
    var pedido = it.tipo_item === "a_pedido";
    var ph = pedido ? "URL https:// o suba un archivo" : "URL opcional (si el inventario no tiene foto)";
    return '<tr class="item-extra"><td></td><td colspan="9">' +
      '<div class="d-flex gap-2 align-items-start">' +
      '<div style="min-width:60px">' + thumbHtml(it) + '</div>' +
      '<div class="flex-grow-1">' +
      '<textarea class="form-control form-control-sm mb-1" rows="2" data-i="' + i + '" data-k="descripcion_detallada" placeholder="Descripción detallada / especificaciones (opcional)">' +
      crmEsc(it.descripcion_detallada || "") + '</textarea>' +
      (it.precio_badge ? '<div class="badge-ultimo-precio mb-1">' + crmEsc(it.precio_badge) + '</div>' : '') +
      '<input class="form-control form-control-sm mb-1" data-i="' + i + '" data-k="imagen_url" placeholder="' + ph + '" value="' + crmEsc(it.imagen_url || "") + '">' +
      '<input class="form-control form-control-sm" type="file" accept="image/png,image/jpeg" data-img="' + i + '">' +
      '</div></div></td><td></td></tr>';
  }

  function render() {
    var tb = document.querySelector("#tablaItems tbody");
    tb.innerHTML = items.map(function (it, i) {
      var warn = !esLibre(it) && it.stock != null && Number(it.stock) < Number(it.cantidad);
      var libre = esLibre(it);
      var pedido = it.tipo_item === "a_pedido";
      return '<tr>' +
        '<td>' + tipoBadge(it) + '</td>' +
        '<td>' + (libre ? '<input class="form-control form-control-sm" value="' + crmEsc(it.codigo || "") + '" data-i="' + i + '" data-k="codigo">' : crmEsc(it.codigo || "")) + '</td>' +
        '<td>' + (libre ? '<input class="form-control form-control-sm" value="' + crmEsc(it.descripcion || "") + '" data-i="' + i + '" data-k="descripcion">' : crmEsc(it.descripcion || "")) + '</td>' +
        '<td>' + marcaCell(it, i) + '</td>' +
        '<td><span class="badge badge-stock ' + (warn ? "low" : "") + '">' + (libre || it.stock == null ? "—" : it.stock) + '</span></td>' +
        '<td><input class="form-control form-control-sm" type="number" min="1" value="' + it.cantidad + '" data-i="' + i + '" data-k="cantidad"></td>' +
        '<td><input class="form-control form-control-sm" type="number" min="0" value="' + it.precio_unitario + '" data-i="' + i + '" data-k="precio_unitario"></td>' +
        '<td>' + (pedido ? '<input class="form-control form-control-sm" type="number" min="0" value="' + (it.costo_unitario || 0) + '" data-i="' + i + '" data-k="costo_unitario">' : "—") + '</td>' +
        '<td><input class="form-control form-control-sm" type="number" min="0" max="100" value="' + it.descuento_pct + '" data-i="' + i + '" data-k="descuento_pct"></td>' +
        '<td class="text-end line-sub">' + crmClp(lineSub(it)) + '</td>' +
        '<td><button class="btn btn-sm btn-outline-danger" type="button" data-del="' + i + '">x</button></td>' +
        '</tr>' + extraRow(it, i);
    }).join("");
    updateTotales();
  }

  function updateTotales() {
    var sub = items.reduce(function (s, it) { return s + lineSub(it); }, 0);
    var desc = Number(document.getElementById("descuento").value || 0);
    var neto = Math.max(0, sub - desc);
    var iva = Math.round(neto * 0.19);
    document.getElementById("totales").innerHTML =
      '<div>Subtotal ' + crmClp(sub) + '</div>' +
      '<div>IVA 19% ' + crmClp(iva) + '</div>' +
      '<div class="fw-bold">Total ' + crmClp(neto + iva) + '</div>';
  }

  function addProducto(p) {
    var empId = Number(document.getElementById("empresa_id").value || 0);
    var listaId = Number(document.getElementById("lista_precio_id").value || 0);
    var url = "api/precios.php?empresa_id=" + encodeURIComponent(empId) + "&producto_id=" + encodeURIComponent(p.id);
    if (listaId > 0) url += "&lista_precio_id=" + encodeURIComponent(listaId);
    crmApi(url).then(function (d) {
      var pr = d.precio || {};
      items.push({
        tipo_item: "producto",
        producto_id: p.id,
        codigo: p.sku || p.codigo,
        descripcion: p.nombre,
        descripcion_detallada: (p.descripcion && p.descripcion !== p.nombre) ? p.descripcion : "",
        imagen_url: p.imagen_url || "",
        cantidad: 1,
        precio_unitario: pr.precio_unitario != null ? pr.precio_unitario : p.precio_unitario,
        descuento_pct: 0,
        stock: p.stock,
        precio_origen: pr.origen || "base",
        precio_badge: pr.origen === "historial" ? (pr.badge || "") : ""
      });
      buscar.value = "";
      sug.style.display = "none";
      render();
    }).catch(function (e) { crmToast(e.message, true); });
  }

  crmApi("api/empresas.php").then(function (d) {
    empresasCache = d.empresas || [];
    document.getElementById("empresa_id").innerHTML = empresasCache.map(function (e) {
      return '<option value="' + e.id + '">' + e.razon_social + '</option>';
    }).join("");
    loadContactos(document.getElementById("empresa_id").value);
    aplicarListaEmpresa();
  }).catch(function (e) { crmToast(e.message, true); });

  function loadContactos(empresaId) {
    var sel = document.getElementById("contacto_id");
    sel.innerHTML = '<option value="">(sin contacto)</option>';
    if (!empresaId) return;
    crmApi("api/contactos.php?empresa_id=" + empresaId).then(function (d) {
      sel.innerHTML = '<option value="">(sin contacto)</option>' + (d.contactos || []).map(function (c) {
        return '<option value="' + c.id + '">' + c.nombre + ' ' + (c.apellido || "") + (c.email ? ' · ' + c.email : '') + '</option>';
      }).join("");
    }).catch(function (e) { crmToast(e.message, true); });
  }
  document.getElementById("empresa_id").addEventListener("change", function () {
    loadContactos(this.value);
    aplicarListaEmpresa();
  });

  crmApi("api/marcas.php").then(function (d) {
    marcasCatalogo = d.marcas || [];
    var marcas = marcasCatalogo;
    document.getElementById("marcasBox").innerHTML = marcas.map(function (m) {
      var chk = false;
      var img = m.existe_archivo ? '<img src="' + m.url + '" alt="" style="max-height:22px;max-width:70px" class="me-1">' : '';
      return '<label class="form-check d-flex align-items-center gap-1 me-2 mb-1">' +
        '<input class="form-check-input" type="checkbox" value="' + m.id + '" ' + (chk ? 'checked' : '') + '>' +
        img + '<span class="small">' + (m.nombre || m.archivo) + '</span></label>';
    }).join("") || '<span class="small text-secondary">No hay marcas cargadas.</span>';
  }).catch(function (e) { crmToast(e.message, true); });

  crmApi("api/cotizaciones.php?proximo=1").then(function (d) {
    var folio = d.proximo_folio || "";
    if (!folio) {
      return;
    }
    var badge = document.getElementById("folioBadge");
    badge.textContent = "Cotización Nueva (Próximo Nº: " + folio + ")";
    badge.classList.add("badge-folio-new");
    badge.classList.remove("badge-folio-ok");
  }).catch(function (e) { crmToast(e.message, true); });

  crmApi("api/listas_precios.php").then(function (d) {
    listasCache = d.listas || [];
    aplicarListaEmpresa();
  }).catch(function (e) { crmToast(e.message, true); });

  crmApi("api/vendedores.php").then(function (d) {
    document.getElementById("vendedor_id").innerHTML = '<option value="">(según usuario)</option>' +
      (d.vendedores || []).filter(function (v) { return Number(v.activo) === 1; }).map(function (v) {
        return '<option value="' + v.id + '">' + v.nombre_completo + ' · ' + Number(v.comision_porcentaje).toFixed(2) + '%</option>';
      }).join("");
  }).catch(function (e) { crmToast(e.message, true); });

  buscar.addEventListener("input", function () {
    var q = buscar.value;
    if (timer) {
      clearTimeout(timer);
    }
    timer = setTimeout(function () {
      if (!q) {
        sug.style.display = "none";
        return;
      }
      fetch("api/crear_cotizacion.php?action=buscar_producto&q=" + encodeURIComponent(q), {
        credentials: "same-origin",
        headers: { Accept: "application/json" }
      }).then(function (r) { return r.json(); }).then(function (data) {
        var list = data.productos || [];
        if (!list.length) {
          sug.innerHTML = '<div class="list-group-item small text-secondary">Sin resultados en inventario</div>';
          sug.style.display = "block";
          return;
        }
        sug.innerHTML = list.map(function (p, idx) {
          return '<button type="button" class="list-group-item list-group-item-action" data-idx="' + idx + '">' +
            '<strong>' + (p.sku || p.codigo) + '</strong> · ' + p.nombre +
            ' <span class="small text-secondary">stock ' + p.stock + ' · ' + crmClp(p.precio_unitario) + '</span></button>';
        }).join("");
        sug.style.display = "block";
        sug.querySelectorAll("button").forEach(function (btn) {
          btn.addEventListener("click", function () {
            addProducto(list[Number(btn.getAttribute("data-idx"))]);
          });
        });
      }).catch(function (e) { crmToast(e.message || "Error de búsqueda", true); });
    }, 250);
  });

  document.querySelector("#tablaItems tbody").addEventListener("input", function (ev) {
    var i = ev.target.getAttribute("data-i");
    var k = ev.target.getAttribute("data-k");
    if (i == null) {
      return;
    }
    items[i][k] = ev.target.value;
    var row = ev.target.closest("tr");
    if (k === "marca_id") {
      var sel = ev.target;
      var opt = sel.options[sel.selectedIndex];
      items[i].marca_id = Number(ev.target.value || 0);
      if (items[i].marca_id > 0 && opt) {
        items[i].marca_nombre = opt.text;
        var nom = row ? row.querySelector('[data-k="marca_nombre"]') : null;
        if (nom) nom.value = opt.text;
      }
    }
    if (row) {
      var subCell = row.querySelector(".line-sub");
      if (subCell) {
        subCell.textContent = crmClp(lineSub(items[i]));
      }
    }
    updateTotales();
  });
  document.querySelector("#tablaItems tbody").addEventListener("click", function (ev) {
    var del = ev.target.getAttribute("data-del");
    if (del == null) {
      return;
    }
    items.splice(Number(del), 1);
    render();
  });
  document.querySelector("#tablaItems tbody").addEventListener("change", function (ev) {
    if (ev.target.getAttribute("data-k") === "marca_id") {
      ev.target.dispatchEvent(new Event("input", { bubbles: true }));
    }
    var idx = ev.target.getAttribute("data-img");
    if (idx == null || !ev.target.files || !ev.target.files[0]) {
      return;
    }
    var fd = new FormData();
    fd.append("archivo", ev.target.files[0]);
    crmApi("api/cotizacion_item_imagen.php", { method: "POST", body: fd }).then(function (d) {
      items[Number(idx)].imagen_url = d.imagen_url;
      render();
    }).catch(function (e) { crmToast(e.message, true); });
  });
  document.getElementById("btnPedido").addEventListener("click", function () {
    items.push({
      tipo_item: "a_pedido",
      es_a_pedido: 1,
      producto_id: null,
      marca_id: 0,
      marca_nombre: "",
      codigo: "PEDIDO",
      descripcion: "",
      descripcion_detallada: "",
      imagen_url: "",
      cantidad: 1,
      precio_unitario: 0,
      costo_unitario: 0,
      descuento_pct: 0,
      stock: null
    });
    render();
  });
  document.getElementById("btnServicio").addEventListener("click", function () {
    items.push({
      tipo_item: "servicio",
      producto_id: null,
      codigo: "SERV",
      descripcion: "",
      descripcion_detallada: "",
      imagen_url: "",
      cantidad: 1,
      precio_unitario: 0,
      descuento_pct: 0,
      stock: null
    });
    render();
  });
  document.getElementById("descuento").addEventListener("input", render);

  document.getElementById("btnGuardar").addEventListener("click", function () {
    var okMsg = document.getElementById("okMsg");
    okMsg.hidden = true;
    fetch("api/crear_cotizacion.php?action=guardar", {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({
        empresa_id: Number(document.getElementById("empresa_id").value || 0),
        contacto_id: Number(document.getElementById("contacto_id").value || 0),
        vendedor_id: Number(document.getElementById("vendedor_id").value || 0),
        lista_precio_id: Number(document.getElementById("lista_precio_id").value || 0),
        estado: document.getElementById("estado").value,
        descuento: Number(document.getElementById("descuento").value || 0),
        moneda: document.getElementById("moneda").value,
        validez_oferta: document.getElementById("validez_oferta").value,
        condiciones_pago: document.getElementById("condiciones_pago").value,
        plazo_entrega: document.getElementById("plazo_entrega").value,
        lugar_entrega: document.getElementById("lugar_entrega").value,
        notas: document.getElementById("notas").value,
        marca_ids: Array.prototype.map.call(document.querySelectorAll("#marcasBox input:checked"), function (el) {
          return Number(el.value);
        }),
        items: items
      })
    }).then(function (r) { return r.json().then(function (d) { return { r: r, d: d }; }); })
      .then(function (pack) {
        if (!pack.r.ok || pack.d.ok === false) {
          throw new Error(pack.d.error || "No se pudo guardar");
        }
        okMsg.innerHTML = "Guardada " + pack.d.folio + " · Total " + crmClp(pack.d.total) +
          ' · <a href="api/cotizacion_pdf.php?id=' + pack.d.id + '" target="_blank">Descargar PDF</a>';
        okMsg.hidden = false;
        crmToast("Cotización " + pack.d.folio + " creada");
        var badge = document.getElementById("folioBadge");
        badge.textContent = pack.d.folio;
        badge.classList.remove("badge-folio-new");
        badge.classList.add("badge-folio-ok");
        items = [];
        render();
      }).catch(function (e) { crmToast(e.message, true); });
  });

  render();
})();
</script>
<?php crm_layout_end(); ?>
