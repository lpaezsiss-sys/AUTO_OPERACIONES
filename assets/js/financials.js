(function () {
  "use strict";

  if (window.COMEX_TAB !== "financials") {
    return;
  }

  var opId = Number(window.COMEX_OPERACION_ID || 0);
  var pack = null;
  var catalogo = {};
  var lastEst = null;
  var lastReal = null;

  function gastosDe(calc, fallbackCat) {
    if (calc && calc.gastos && calc.gastos.length) {
      return calc.gastos;
    }
    return Object.keys(fallbackCat).map(function (k) {
      var m = fallbackCat[k];
      return { codigo: k, nombre: m.nombre, ambito: m.ambito, moneda: m.moneda, monto: 0, cif: m.cif };
    });
  }

  function itemsDe(op, calc) {
    if (calc && calc.items && calc.items.length) {
      return calc.items;
    }
    return (op.items || []).map(function (it) {
      return {
        id: it.id,
        sku: it.sku,
        descripcion: it.descripcion,
        cantidad: it.cantidad,
        fob_unitario: it.precio_unitario || it.fob_unitario || 0,
      };
    });
  }

  function renderGastos() {
    var estG = gastosDe(lastEst, catalogo);
    var realBy = {};
    gastosDe(lastReal, catalogo).forEach(function (g) { realBy[g.codigo] = g; });
    var tb = document.querySelector("#sheetGastos tbody");
    tb.innerHTML = estG.map(function (g) {
      var r = realBy[g.codigo] || g;
      var delta = (Number(r.monto) || 0) - (Number(g.monto) || 0);
      return '<tr data-codigo="' + crmEsc(g.codigo) + '" data-nombre="' + crmEsc(g.nombre) +
        '" data-ambito="' + crmEsc(g.ambito) + '" data-cif="' + (g.cif ? "1" : "0") + '">' +
        "<td><div class=\"fw-semibold\">" + crmEsc(g.nombre) + "</div></td>" +
        "<td class=\"small text-secondary\">" + crmEsc(g.ambito) + (g.cif ? " · CIF" : "") + "</td>" +
        '<td><select class="form-select form-select-sm g-moneda">' +
        ["CLP", "USD", "EUR"].map(function (m) {
          return '<option value="' + m + '"' + (m === (g.moneda || "CLP") ? " selected" : "") + ">" + m + "</option>";
        }).join("") + "</select></td>" +
        '<td><input class="form-control form-control-sm g-est sheet-in" value="' + crmEsc(g.monto || 0) + '"></td>' +
        '<td><input class="form-control form-control-sm g-real sheet-in" value="' + crmEsc(r.monto || 0) + '"></td>' +
        '<td class="delta-cell">' + crmClp(delta) + "</td></tr>";
    }).join("");
  }

  function readGastos(which) {
    return Array.prototype.map.call(document.querySelectorAll("#sheetGastos tbody tr"), function (tr) {
      return {
        codigo: tr.getAttribute("data-codigo"),
        nombre: tr.getAttribute("data-nombre"),
        ambito: tr.getAttribute("data-ambito"),
        cif: tr.getAttribute("data-cif") === "1",
        moneda: tr.querySelector(".g-moneda").value,
        monto: crmParseNum(tr.querySelector(which === "real" ? ".g-real" : ".g-est").value),
      };
    });
  }

  function readItems() {
    return Array.prototype.map.call(document.querySelectorAll("#sheetItems tbody tr.sheet-item"), function (tr) {
      return {
        operacion_item_id: Number(tr.getAttribute("data-id") || 0),
        sku: tr.getAttribute("data-sku"),
        descripcion: tr.getAttribute("data-desc") || "",
        cantidad: crmParseNum(tr.querySelector(".i-cant").value),
        fob_unitario: crmParseNum(tr.querySelector(".i-fob").value),
      };
    });
  }

  function payload(version) {
    return {
      operacion_id: opId,
      version: version,
      moneda_origen: document.getElementById("finMoneda").value,
      tipo_cambio_usd: crmParseNum(document.getElementById("finTcUsd").value),
      tipo_cambio_eur: crmParseNum(document.getElementById("finTcEur").value),
      iva_pct: crmParseNum(document.getElementById("finIva").value),
      notas: document.getElementById("finNotas").value,
      gastos: readGastos(version === "REAL" ? "real" : "est"),
      items: readItems(),
    };
  }

  function liveBody() {
    var p = payload("ESTIMADA");
    return Object.assign({}, p, {
      gastos_estimada: readGastos("est"),
      gastos_real: readGastos("real"),
    });
  }

  function recalc() {
    var hint = document.getElementById("sheetHint");
    try {
      var base = payload("ESTIMADA");
      lastEst = crmLandedCalcular(Object.assign({}, base, { version: "ESTIMADA", gastos: readGastos("est") }));
      lastReal = crmLandedCalcular(Object.assign({}, base, { version: "REAL", gastos: readGastos("real") }));
      var comp = crmLandedComparar(lastEst, lastReal);
      paintItems(lastEst, lastReal);
      paintMatriz(comp);
      paintKpis(lastEst, lastReal, comp);
      paintGastoDeltas();
      hint.textContent = "Recálculo en vivo · factor FOB · IVA " + lastEst.iva_pct + "% CIF";
    } catch (e) {
      hint.textContent = e.message || "No se pudo calcular";
    }
  }

  function paintGastoDeltas() {
    Array.prototype.forEach.call(document.querySelectorAll("#sheetGastos tbody tr"), function (tr) {
      var est = crmParseNum(tr.querySelector(".g-est").value);
      var real = crmParseNum(tr.querySelector(".g-real").value);
      var cell = tr.querySelector(".delta-cell");
      var d = real - est;
      cell.textContent = crmClp(d);
      cell.className = "delta-cell " + (d > 0 ? "delta-up" : (d < 0 ? "delta-down" : ""));
    });
  }

  function paintItems(est, real) {
    var tb = document.querySelector("#sheetItems tbody");
    var bySku = {};
    (real.items || []).forEach(function (it) { bySku[it.sku] = it; });
    tb.innerHTML = (est.items || []).map(function (it) {
      var r = bySku[it.sku] || {};
      return '<tr class="sheet-item" data-id="' + crmEsc(it.operacion_item_id || it.id || 0) +
        '" data-sku="' + crmEsc(it.sku) + '" data-desc="' + crmEsc(it.descripcion || "") + '">' +
        "<td><code>" + crmEsc(it.sku) + "</code></td>" +
        '<td><input class="form-control form-control-sm i-cant sheet-in" value="' + crmEsc(it.cantidad) + '"></td>' +
        '<td><input class="form-control form-control-sm i-fob sheet-in" value="' + crmEsc(it.fob_unitario) + '"></td>' +
        "<td>" + crmNum((it.factor || 0) * 100, 1) + "%</td>" +
        "<td>" + crmClp(it.cif_clp) + "</td><td>" + crmClp(it.iva_clp) + "</td>" +
        "<td class=\"fw-semibold\">" + crmClp(it.landed_total_clp) + "</td>" +
        "<td>" + crmClp(it.landed_unitario_clp) + "</td>" +
        "<td>" + crmNum(it.landed_unitario_usd, 4) + "</td>" +
        "<td>" + crmClp(r.cif_clp) + "</td><td>" + crmClp(r.iva_clp) + "</td>" +
        "<td class=\"fw-semibold\">" + crmClp(r.landed_total_clp) + "</td>" +
        "<td>" + crmClp(r.landed_unitario_clp) + "</td>" +
        "<td>" + crmNum(r.landed_unitario_usd, 4) + "</td></tr>";
    }).join("") || '<tr><td colspan="14" class="text-secondary">La operación no tiene ítems FOB. Agregue SKU para prorratear.</td></tr>';
  }

  function paintMatriz(comp) {
    var labels = {
      fob_clp: "FOB CLP",
      cif_clp: "CIF",
      iva_clp: "IVA aduanero 19%",
      gastos_locales_clp: "Gastos locales",
      landed_clp: "Landed CLP",
      landed_usd: "Landed USD",
    };
    var tot = (comp && comp.totales) || {};
    document.querySelector("#sheetMatriz tbody").innerHTML = Object.keys(labels).map(function (k) {
      var d = tot[k] || {};
      var cls = (d.delta || 0) > 0 ? "delta-up" : ((d.delta || 0) < 0 ? "delta-down" : "");
      var pct = d.delta_pct == null ? "" : " (" + d.delta_pct + "%)";
      var fmt = k === "landed_usd" ? function (n) { return "US$ " + crmNum(n, 2); } : crmClp;
      return "<tr><td>" + labels[k] + "</td><td>" + fmt(d.estimada) + "</td><td>" + fmt(d.real) +
        '</td><td class="' + cls + '">' + fmt(d.delta) + pct + "</td></tr>";
    }).join("");
  }

  function paintKpis(est, real, comp) {
    var tE = est.totales || {};
    var tR = real.totales || {};
    var d = (comp.totales && comp.totales.landed_clp) || {};
    document.getElementById("finKpis").innerHTML = [
      ["Landed est CLP", crmClp(tE.landed_clp)],
      ["Landed real CLP", crmClp(tR.landed_clp)],
      ["Delta landed", crmClp(d.delta || 0)],
      ["Unitario USD (real)", "US$ " + crmNum((real.items && real.items[0] && real.items[0].landed_unitario_usd) || 0, 4)],
    ].map(function (it) {
      return '<div class="col-6 col-xl-3"><div class="card kpi p-3"><div class="kpi-label">' + it[0] +
        '</div><div class="kpi-value" style="font-size:1.15rem">' + it[1] + "</div></div></div>";
    }).join("");
  }

  function bindSheet() {
    var timer = null;
    document.getElementById("tabFinanzas").addEventListener("input", function (ev) {
      if (ev.target && ev.target.classList.contains("sheet-in")) {
        clearTimeout(timer);
        timer = setTimeout(recalc, 80);
      }
    });
    document.getElementById("tabFinanzas").addEventListener("change", function (ev) {
      if (ev.target && (ev.target.classList.contains("g-moneda") || ev.target.id === "finMoneda")) {
        recalc();
      }
    });
  }

  function save(version) {
    crmApi("api/landed.php", { method: "POST", body: Object.assign(payload(version), { action: "guardar" }) })
      .then(function () {
        crmToast("Versión " + version + " guardada");
        return load(false);
      })
      .catch(function (e) { crmToast(e.message, true); });
  }

  function exportFile(action) {
    crmApi("api/landed.php", { method: "POST", body: Object.assign(liveBody(), { action: action }) })
      .then(function (d) {
        var p = d.xlsx_path || d.pdf_path;
        crmToast(action === "xlsx" ? "Excel generado" : "PDF generado");
        if (p) {
          window.open(p, "_blank");
        }
      })
      .catch(function (e) { crmToast(e.message, true); });
  }

  async function load(first) {
    if (!opId) {
      crmToast("Indique ?id= de la operación", true);
      return;
    }
    pack = await crmApi("api/landed.php?operacion_id=" + opId);
    catalogo = pack.catalogo_gastos || {};
    var op = pack.operacion || {};
    lastEst = pack.estimada && pack.estimada.calculo ? pack.estimada.calculo : null;
    lastReal = pack.real && pack.real.calculo ? pack.real.calculo : null;
    if (!lastEst) {
      lastEst = {
        moneda_origen: "USD",
        tipo_cambio_usd: 900,
        tipo_cambio_eur: 1050,
        iva_pct: window.COMEX_IVA_PCT || 19,
        gastos: gastosDe(null, catalogo),
        items: itemsDe(op, null),
      };
    }
    if (!lastReal) {
      lastReal = Object.assign({}, lastEst, { version: "REAL" });
    }
    document.getElementById("finMoneda").value = lastEst.moneda_origen || "USD";
    document.getElementById("finTcUsd").value = lastEst.tipo_cambio_usd || 900;
    document.getElementById("finTcEur").value = lastEst.tipo_cambio_eur || 1050;
    document.getElementById("finNotas").value = (pack.estimada && pack.estimada.notas) || "";
    renderGastos();
    paintItems({ items: itemsDe(op, lastEst) }, { items: [] });
    if (first) {
      bindSheet();
    }
    recalc();
  }

  document.getElementById("btnSaveEst").addEventListener("click", function () { save("ESTIMADA"); });
  document.getElementById("btnSaveReal").addEventListener("click", function () { save("REAL"); });
  document.getElementById("btnXlsx").addEventListener("click", function () { exportFile("xlsx"); });
  document.getElementById("btnPdfMx").addEventListener("click", function () { exportFile("pdf_matriz"); });

  load(true).catch(function (e) { crmToast(e.message, true); });
})();
