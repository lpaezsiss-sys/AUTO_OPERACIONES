(function () {
  "use strict";

  if (window.COMEX_TAB && window.COMEX_TAB !== "items") {
    return;
  }

  var opId = Number(window.COMEX_OPERACION_ID || 0);
  var op = null;
  var modalVincular = null;
  var modalEditar = null;
  var modalQuitar = null;

  function badgeOrigen(it) {
    if (it.is_custom) {
      return '<span class="badge-eval">Evaluación</span>';
    }
    return '<span class="badge-cat">Catálogo</span>';
  }

  function pintarAlerta(items) {
    var pend = (items || []).filter(function (it) { return it.is_custom; });
    var el = document.getElementById("alertaEvalItems");
    if (!pend.length) {
      el.classList.add("d-none");
      el.innerHTML = "";
      return;
    }
    el.classList.remove("d-none");
    el.innerHTML = "Hay <strong>" + pend.length + "</strong> SKU de evaluación sin vincular. Créelos en inventario o vincúlelos al catálogo oficial antes de Entrega/Cierre (ENTRADA a stock).";
  }

  function botonesItem(it) {
    var locked = String(it.movimiento_id || "") !== "";
    var html = '<div class="btn-group btn-group-sm" role="group">' +
      '<button type="button" class="btn btn-outline-secondary btn-editar-item" data-id="' + it.id +
      '" data-sku="' + crmEsc(it.sku) +
      '" data-desc="' + crmEsc(it.descripcion || "") +
      '" data-cant="' + crmEsc(it.cantidad) +
      '" data-fob="' + crmEsc(it.precio_unitario) +
      '" data-locked="' + (locked ? "1" : "0") + '">Editar</button>';
    if (!locked) {
      html += '<button type="button" class="btn btn-outline-danger btn-eliminar-item" data-id="' + it.id +
        '" data-sku="' + crmEsc(it.sku) + '">Quitar</button>';
    }
    if (it.is_custom) {
      html += '<button type="button" class="btn btn-navy btn-vincular" data-id="' + it.id +
        '" data-sku="' + crmEsc(it.sku) + '">Vincular</button>';
    }
    return html + "</div>";
  }

  function renderItems() {
    var tb = document.querySelector("#tablaItemsOp tbody");
    var items = (op && op.items) || [];
    pintarAlerta(items);
    tb.innerHTML = items.map(function (it) {
      var stock = it.stock == null ? "—" : crmEsc(it.stock);
      return "<tr>" +
        "<td><code>" + crmEsc(it.sku) + "</code></td>" +
        "<td>" + badgeOrigen(it) + "</td>" +
        "<td>" + crmEsc(it.descripcion || "") + "</td>" +
        "<td>" + crmEsc(it.cantidad) + "</td>" +
        "<td>" + crmNum(it.precio_unitario, 2) + "</td>" +
        "<td>" + stock + "</td>" +
        "<td>" + botonesItem(it) + "</td></tr>";
    }).join("") || '<tr><td colspan="7" class="text-secondary">Sin ítems. Agregue un SKU de catálogo o un código temporal.</td></tr>';

    Array.prototype.forEach.call(tb.querySelectorAll(".btn-vincular"), function (btn) {
      btn.addEventListener("click", function () {
        document.getElementById("vincularItemId").value = btn.getAttribute("data-id");
        document.getElementById("vincularSkuTemp").textContent = btn.getAttribute("data-sku") || "";
        document.getElementById("vincularSkuOficial").value = btn.getAttribute("data-sku") || "";
        modalVincular.show();
      });
    });
    Array.prototype.forEach.call(tb.querySelectorAll(".btn-editar-item"), function (btn) {
      btn.addEventListener("click", function () {
        var locked = btn.getAttribute("data-locked") === "1";
        document.getElementById("editItemId").value = btn.getAttribute("data-id");
        document.getElementById("editItemSku").value = btn.getAttribute("data-sku") || "";
        document.getElementById("editItemNombre").value = btn.getAttribute("data-desc") || "";
        document.getElementById("editItemCant").value = btn.getAttribute("data-cant") || "1";
        document.getElementById("editItemFob").value = btn.getAttribute("data-fob") || "0";
        document.getElementById("editItemSku").readOnly = locked;
        document.getElementById("editItemCant").readOnly = locked;
        document.getElementById("editItemLockHint").classList.toggle("d-none", !locked);
        modalEditar.show();
      });
    });
    Array.prototype.forEach.call(tb.querySelectorAll(".btn-eliminar-item"), function (btn) {
      btn.addEventListener("click", function () {
        document.getElementById("delItemId").value = btn.getAttribute("data-id");
        document.getElementById("delItemSku").textContent = btn.getAttribute("data-sku") || "";
        modalQuitar.show();
      });
    });
  }

  function fillDatalist(skus) {
    document.getElementById("listaSkuCatalogo").innerHTML = (skus || []).map(function (s) {
      return '<option value="' + crmEsc(s) + '">';
    }).join("");
  }

  async function load() {
    if (!opId) {
      crmToast("Indique ?id= de la operación", true);
      return;
    }
    var data = await crmApi("api/operaciones.php?id=" + opId);
    op = data.operacion || data;
    var folio = op.folio || "Operación";
    document.getElementById("tituloOp").textContent = folio + " · Ítems";
    document.getElementById("subOp").textContent = "Catálogo y SKU temporales de evaluación";
    renderItems();
  }

  async function loadCatalogo() {
    try {
      var d = await crmApi("api/fichas.php");
      var rows = d.fichas || d || [];
      fillDatalist(rows.map(function (f) { return f.sku; }).filter(Boolean));
    } catch (e) {
      fillDatalist([]);
    }
  }

  document.getElementById("formItem").addEventListener("submit", function (ev) {
    ev.preventDefault();
    var sku = document.getElementById("itemSku").value.trim();
    var nombre = document.getElementById("itemNombre").value.trim();
    crmApi("api/operaciones.php?action=agregar_items&id=" + encodeURIComponent(opId), {
      method: "POST",
      body: {
        action: "agregar_items",
        id: opId,
        items: [{
          sku: sku,
          descripcion: nombre,
          nombre: nombre,
          cantidad: crmParseNum(document.getElementById("itemCant").value),
          precio_unitario: crmParseNum(document.getElementById("itemFob").value),
        }],
      },
    }).then(function () {
      crmToast("Ítem agregado");
      document.getElementById("itemSku").value = "";
      document.getElementById("itemNombre").value = "";
      document.getElementById("itemCant").value = "1";
      document.getElementById("itemFob").value = "0";
      return load();
    }).catch(function (e) { crmToast(e.message, true); });
  });

  document.getElementById("formVincular").addEventListener("submit", function (ev) {
    ev.preventDefault();
    var itemId = Number(document.getElementById("vincularItemId").value);
    crmApi("api/operaciones.php?action=vincular&item_id=" + encodeURIComponent(itemId), {
      method: "POST",
      body: {
        action: "vincular",
        item_id: itemId,
        sku_oficial: document.getElementById("vincularSkuOficial").value.trim(),
      },
    }).then(function () {
      crmToast("SKU vinculado al catálogo oficial");
      modalVincular.hide();
      return load();
    }).catch(function (e) { crmToast(e.message, true); });
  });

  document.getElementById("formEditarItem").addEventListener("submit", function (ev) {
    ev.preventDefault();
    var itemId = Number(document.getElementById("editItemId").value);
    crmApi("api/operaciones.php?action=actualizar_item&item_id=" + encodeURIComponent(itemId), {
      method: "POST",
      body: {
        action: "actualizar_item",
        item_id: itemId,
        sku: document.getElementById("editItemSku").value.trim(),
        nombre: document.getElementById("editItemNombre").value.trim(),
        descripcion: document.getElementById("editItemNombre").value.trim(),
        cantidad: crmParseNum(document.getElementById("editItemCant").value),
        precio_unitario: crmParseNum(document.getElementById("editItemFob").value),
      },
    }).then(function () {
      crmToast("Ítem actualizado");
      modalEditar.hide();
      return load();
    }).catch(function (e) { crmToast(e.message, true); });
  });

  document.getElementById("btnDelItemConfirmar").addEventListener("click", function () {
    var itemId = Number(document.getElementById("delItemId").value);
    crmApi("api/operaciones.php?action=eliminar_item&item_id=" + encodeURIComponent(itemId), {
      method: "POST",
      body: {
        action: "eliminar_item",
        item_id: itemId,
      },
    }).then(function () {
      crmToast("Ítem eliminado");
      modalQuitar.hide();
      return load();
    }).catch(function (e) { crmToast(e.message, true); });
  });

  modalVincular = bootstrap.Modal.getOrCreateInstance(document.getElementById("modalVincular"));
  modalEditar = bootstrap.Modal.getOrCreateInstance(document.getElementById("modalEditarItem"));
  modalQuitar = bootstrap.Modal.getOrCreateInstance(document.getElementById("modalEliminarItem"));
  loadCatalogo().catch(function () { /* datalist opcional */ });
  load().catch(function (e) { crmToast(e.message, true); });
})();
