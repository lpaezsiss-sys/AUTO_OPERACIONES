(function () {
  "use strict";

  function crmWithQuery(path, params) {
    var extra = [];
    Object.keys(params || {}).forEach(function (k) {
      var v = params[k];
      if (v == null || v === "") {
        return;
      }
      extra.push(encodeURIComponent(k) + "=" + encodeURIComponent(String(v)));
    });
    if (!extra.length) {
      return path;
    }
    var qi = path.indexOf("?");
    var existing = {};
    if (qi >= 0) {
      path.slice(qi + 1).split("&").forEach(function (pair) {
        var key = decodeURIComponent((pair.split("=")[0] || "").replace(/\+/g, " "));
        if (key) {
          existing[key] = true;
        }
      });
    }
    var add = extra.filter(function (pair) {
      var key = decodeURIComponent(pair.split("=")[0] || "");
      return key && !existing[key];
    });
    if (!add.length) {
      return path;
    }
    return path + (qi >= 0 ? "&" : "?") + add.join("&");
  }

  window.crmApi = async function (path, options) {
    options = options || {};
    var headers = {
      Accept: "application/json",
      "Cache-Control": "no-cache",
      Pragma: "no-cache",
      "X-Requested-With": "XMLHttpRequest",
    };
    if (options.body) {
      headers["Content-Type"] = "application/json";
    }
    var url = path;
    if (options.body && typeof options.body === "object") {
      url = crmWithQuery(path, {
        action: options.body.action || "",
        id: options.body.id || options.body.operacion_id || "",
      });
    }
    var res = await fetch(url, {
      credentials: "include",
      cache: "no-store",
      redirect: "follow",
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
      var msg = data.error || "Error de API";
      if (res.status === 401 && (!data.error || data.error === "No autenticado")) {
        msg = "No autenticado. Recargue e inicie sesión.";
      }
      var err = new Error(msg);
      err.status = res.status;
      err.codigo = data.codigo || "";
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
