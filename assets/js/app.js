(function () {
  "use strict";

  window.crmApi = async function (path, options) {
    options = options || {};
    var headers = {
      Accept: "application/json",
      "Cache-Control": "no-cache",
      Pragma: "no-cache",
    };
    if (options.body) {
      headers["Content-Type"] = "application/json";
    }
    var res = await fetch(path, {
      credentials: "same-origin",
      cache: "no-store",
      method: options.method || "GET",
      headers: Object.assign(headers, options.headers || {}),
      body: options.body ? JSON.stringify(options.body) : undefined,
    });
    var data = {};
    try {
      data = await res.json();
    } catch (e) {
      data = { ok: false, success: false, error: "Respuesta inválida (HTTP " + res.status + ")" };
    }
    if (!res.ok || data.ok === false || data.success === false) {
      var err = new Error(data.error || "Error de API");
      err.status = res.status;
      throw err;
    }
    return data;
  };

  window.crmEsc = function (s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  };

  window.crmClp = function (n) {
    return new Intl.NumberFormat("es-CL", {
      style: "currency",
      currency: "CLP",
      maximumFractionDigits: 0,
    }).format(Number(n || 0));
  };

  window.crmNum = function (n, d) {
    d = d == null ? 2 : d;
    return new Intl.NumberFormat("es-CL", {
      minimumFractionDigits: d,
      maximumFractionDigits: d,
    }).format(Number(n || 0));
  };

  window.crmToast = function (msg, isError) {
    var el = document.getElementById("crmToast");
    var body = document.getElementById("crmToastBody");
    if (!el || !body || typeof bootstrap === "undefined") {
      window.alert(msg);
      return;
    }
    body.textContent = msg;
    el.classList.toggle("text-bg-danger", !!isError);
    el.classList.toggle("text-bg-dark", !isError);
    bootstrap.Toast.getOrCreateInstance(el).show();
  };

  window.crmParseNum = function (v) {
    if (typeof v === "number") {
      return isFinite(v) ? v : 0;
    }
    if (v == null) {
      return 0;
    }
    var s = String(v).trim();
    if (s === "") {
      return 0;
    }
    if (/^-?\d+(\.\d+)?$/.test(s)) {
      return parseFloat(s);
    }
    var stripped = s.replace(/[^\d,.\-]/g, "");
    var normalized = stripped.replace(/\./g, "").replace(",", ".");
    var n = parseFloat(normalized);
    return isFinite(n) ? n : 0;
  };
})();
