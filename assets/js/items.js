(function () {
  "use strict";

  if (window.COMEX_TAB && window.COMEX_TAB !== "items") {
    return;
  }

  var opId = Number(window.COMEX_OPERACION_ID || 0);
  var op = null;
  var modal = null;

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

  function renderItems() {
    var tb = document.querySelector("#tablaItemsOp tbody");
    var items = (op && op.items) || [];
    pintarAlerta(items);
    tb.innerHTML = items.map(function (it) {
      var stock = it.stock == null ? "—" : crmEsc(it.stock);
      var btn = it.is_custom
        ? '<button type="button" class="btn btn-sm btn-navy btn-vincular" data-id="' + it.id +
          '" data-sku="' + crmEsc(it.sku) + '">Vincular</button>'
        : "";
      return "<tr>" +
        "<td><code>" + crmEsc(it.sku) + "</code></td>" +
        "<td>" + badgeOrigen(it) + "</td>" +
        "<td>" + crmEsc(it.descripcion || "") + "</td>" +
        "<td>" + crmEsc(it.cantidad) + "</td>" +
        "<td>" + crmNum(it.precio_unitario, 2) + "</td>" +
        "<td>" + stock + "</td>" +
        "<td>" + btn + "</td></tr>";
    }).join("") || '<tr><td colspan="7" class="text-secondary">Sin ítems. Agregue un SKU de catálogo o un código temporal.</td></tr>';
    Array.prototype.forEach.call(tb.querySelectorAll(".btn-vincular"), function (btn) {
      btn.addEventListener("click", function () {
        document.getElementById("vincularItemId").value = btn.getAttribute("data-id");
        document.getElementById("vincularSkuTemp").textContent = btn.getAttribute("data-sku") || "";
        document.getElementById("vincularSkuOficial").value = btn.getAttribute("data-sku") || "";
        modal.show();
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
    crmApi("api/operaciones.php", {
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
    crmApi("api/operaciones.php", {
      method: "POST",
      body: {
        action: "vincular",
        item_id: Number(document.getElementById("vincularItemId").value),
        sku_oficial: document.getElementById("vincularSkuOficial").value.trim(),
      },
    }).then(function () {
      crmToast("SKU vinculado al catálogo oficial");
      modal.hide();
      return load();
    }).catch(function (e) { crmToast(e.message, true); });
  });

  modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("modalVincular"));
  loadCatalogo().catch(function () { /* datalist opcional */ });
  load().catch(function (e) { crmToast(e.message, true); });
})();
