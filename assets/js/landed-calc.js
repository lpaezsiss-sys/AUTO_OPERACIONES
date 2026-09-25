(function (global) {
  "use strict";

  function roundN(n, d) {
    var f = Math.pow(10, d);
    return Math.round((Number(n) || 0) * f) / f;
  }

  function prorratear(total, pesos) {
    var n = pesos.length;
    if (!n) {
      return [];
    }
    var suma = 0;
    for (var i = 0; i < n; i++) {
      suma += Number(pesos[i]) || 0;
    }
    if (suma <= 0) {
      throw new Error("El FOB total debe ser mayor que 0 para prorratear");
    }
    var out = [];
    var acc = 0;
    for (var j = 0; j < n; j++) {
      if (j === n - 1) {
        out.push(roundN(total - acc, 2));
        break;
      }
      var parte = roundN(total * ((Number(pesos[j]) || 0) / suma), 2);
      out.push(parte);
      acc += parte;
    }
    return out;
  }

  function aClp(monto, moneda, tcUsd, tcEur) {
    moneda = String(moneda || "CLP").toUpperCase();
    if (moneda === "CLP") {
      return monto;
    }
    if (moneda === "USD") {
      return monto * tcUsd;
    }
    if (moneda === "EUR") {
      return monto * tcEur;
    }
    throw new Error("Moneda no soportada: " + moneda);
  }

  function aUsd(clp, tcUsd, dec) {
    if (!(tcUsd > 0)) {
      return 0;
    }
    return roundN(clp / tcUsd, dec == null ? 2 : dec);
  }

  function calcular(input) {
    input = input || {};
    var moneda = String(input.moneda_origen || "USD").toUpperCase();
    var tcUsd = Number(input.tipo_cambio_usd) || 0;
    var tcEur = Number(input.tipo_cambio_eur) || 0;
    var ivaPct = Number(input.iva_pct);
    if (!(ivaPct >= 0)) {
      ivaPct = 19;
    }
    var gastosIn = input.gastos || [];
    var gastos = [];
    var cifClp = 0;
    var localesClp = 0;
    var origenClp = 0;
    for (var g = 0; g < gastosIn.length; g++) {
      var raw = gastosIn[g] || {};
      var monedaG = String(raw.moneda || "CLP").toUpperCase();
      var monto = Number(raw.monto) || 0;
      var clp = roundN(aClp(monto, monedaG, tcUsd, tcEur), 2);
      var cif = !!raw.cif;
      var ambito = String(raw.ambito || "LOCAL").toUpperCase();
      gastos.push({
        codigo: raw.codigo,
        nombre: raw.nombre,
        ambito: ambito,
        moneda: monedaG,
        monto: monto,
        monto_clp: clp,
        cif: cif,
      });
      if (cif) {
        cifClp += clp;
      } else {
        localesClp += clp;
      }
      if (ambito === "ORIGEN") {
        origenClp += clp;
      }
    }
    var itemsIn = input.items || [];
    var prep = [];
    var pesos = [];
    for (var i = 0; i < itemsIn.length; i++) {
      var it = itemsIn[i] || {};
      var qty = Number(it.cantidad) || 0;
      var fobU = Number(it.fob_unitario != null ? it.fob_unitario : it.precio_unitario) || 0;
      if (qty <= 0) {
        throw new Error("Cantidad de ítem debe ser mayor que 0");
      }
      var fobOrig = roundN(qty * fobU, 4);
      prep.push({
        operacion_item_id: Number(it.id || it.operacion_item_id || 0),
        sku: it.sku || "",
        descripcion: it.descripcion || "",
        cantidad: qty,
        fob_unitario: fobU,
        fob_origen: fobOrig,
        fob_clp: roundN(aClp(fobOrig, moneda, tcUsd, tcEur), 2),
        origen: it.origen || "",
        is_custom: !!it.is_custom,
      });
      pesos.push(fobOrig);
    }
    if (!prep.length) {
      throw new Error("La evaluación requiere ítems con FOB");
    }
    var fobTotal = pesos.reduce(function (a, b) { return a + b; }, 0);
    var partesCif = prorratear(cifClp, pesos);
    var partesLoc = prorratear(localesClp, pesos);
    var lineas = [];
    var totFobClp = 0, totCif = 0, totIva = 0, totLoc = 0, totLanded = 0;
    for (var k = 0; k < prep.length; k++) {
      var row = prep[k];
      var cifItem = roundN(row.fob_clp + partesCif[k], 2);
      var ivaItem = roundN(cifItem * (ivaPct / 100), 2);
      var locItem = partesLoc[k];
      var landed = roundN(cifItem + ivaItem + locItem, 2);
      var unit = row.cantidad > 0 ? roundN(landed / row.cantidad, 4) : 0;
      var factor = fobTotal > 0 ? roundN(row.fob_origen / fobTotal, 6) : 0;
      lineas.push(Object.assign({}, row, {
        share: factor,
        factor: factor,
        gastos_cif_clp: partesCif[k],
        cif_clp: cifItem,
        iva_clp: ivaItem,
        gastos_locales_clp: locItem,
        landed_total_clp: landed,
        landed_unitario_clp: unit,
        fob_usd: aUsd(row.fob_clp, tcUsd, 4),
        cif_usd: aUsd(cifItem, tcUsd, 4),
        iva_usd: aUsd(ivaItem, tcUsd, 4),
        landed_total_usd: aUsd(landed, tcUsd, 4),
        landed_unitario_usd: aUsd(unit, tcUsd, 4),
      }));
      totFobClp += row.fob_clp;
      totCif += cifItem;
      totIva += ivaItem;
      totLoc += locItem;
      totLanded += landed;
    }
    var totales = {
      fob_origen: roundN(fobTotal, 4),
      fob_clp: roundN(totFobClp, 2),
      gastos_origen_clp: roundN(origenClp, 2),
      gastos_cif_clp: roundN(cifClp, 2),
      cif_clp: roundN(totCif, 2),
      iva_clp: roundN(totIva, 2),
      gastos_locales_clp: roundN(totLoc, 2),
      landed_clp: roundN(totLanded, 2),
    };
    totales.fob_usd = aUsd(totales.fob_clp, tcUsd, 2);
    totales.cif_usd = aUsd(totales.cif_clp, tcUsd, 2);
    totales.iva_usd = aUsd(totales.iva_clp, tcUsd, 2);
    totales.gastos_locales_usd = aUsd(totales.gastos_locales_clp, tcUsd, 2);
    totales.gastos_origen_usd = aUsd(totales.gastos_origen_clp, tcUsd, 2);
    totales.landed_usd = aUsd(totales.landed_clp, tcUsd, 2);
    return {
      version: input.version || "ESTIMADA",
      moneda_origen: moneda,
      tipo_cambio_usd: tcUsd,
      tipo_cambio_eur: tcEur,
      iva_pct: ivaPct,
      gastos: gastos,
      items: lineas,
      totales: totales,
    };
  }

  function comparar(est, real) {
    var keys = ["fob_clp", "cif_clp", "iva_clp", "gastos_locales_clp", "gastos_origen_clp", "landed_clp", "landed_usd"];
    var tot = {};
    keys.forEach(function (k) {
      var e = Number((est && est.totales && est.totales[k]) || 0);
      var r = Number((real && real.totales && real.totales[k]) || 0);
      var d = roundN(r - e, 2);
      tot[k] = { estimada: e, real: r, delta: d, delta_pct: e !== 0 ? roundN((d / e) * 100, 2) : null };
    });
    var bySku = {};
    ((est && est.items) || []).forEach(function (it) { bySku[it.sku] = it; });
    var items = ((real && real.items) || []).map(function (it) {
      var e = bySku[it.sku] || {};
      return {
        sku: it.sku,
        estimada: Number(e.landed_total_clp || 0),
        real: Number(it.landed_total_clp || 0),
        delta: roundN(Number(it.landed_total_clp || 0) - Number(e.landed_total_clp || 0), 2),
        unitario_clp_est: Number(e.landed_unitario_clp || 0),
        unitario_clp_real: Number(it.landed_unitario_clp || 0),
        unitario_usd_est: Number(e.landed_unitario_usd || 0),
        unitario_usd_real: Number(it.landed_unitario_usd || 0),
      };
    });
    return { disponible: !!(est && real), totales: tot, items: items };
  }

  global.crmLandedCalcular = calcular;
  global.crmLandedComparar = comparar;
  global.crmLandedProrratear = prorratear;
})(window);
