(function () {
  "use strict";

  /**
   * Gráfico de barras cartesiano (navy/yellow) equivalente a un BarChart de Recharts.
   * El runtime es PHP + JS vanilla: Recharts requiere React y no se despliega en BlueHosting.
   */
  window.crmBarChart = function (el, series) {
    if (!el) {
      return;
    }
    series = series || [];
    var w = Math.max(el.clientWidth || 640, 320);
    var h = 280;
    var padL = 40;
    var padR = 12;
    var padT = 16;
    var padB = 44;
    var max = 0;
    series.forEach(function (s) {
      var v = Number(s.cantidad || s.value || 0);
      if (v > max) {
        max = v;
      }
    });
    max = Math.max(1, Math.ceil(max));
    var innerW = w - padL - padR;
    var innerH = h - padT - padB;
    var n = Math.max(series.length, 1);
    var gap = 8;
    var barW = Math.max(8, (innerW / n) - gap);
    var ticks = Math.min(4, max);
    var grid = "";
    var i;
    for (i = 0; i <= ticks; i++) {
      var gy = padT + innerH - (innerH * i) / ticks;
      var gv = Math.round((max * i) / ticks);
      grid += '<line x1="' + padL + '" y1="' + gy + '" x2="' + (w - padR) + '" y2="' + gy +
        '" stroke="#d9e2ec" stroke-width="1"/>';
      grid += '<text x="' + (padL - 8) + '" y="' + (gy + 4) +
        '" text-anchor="end" font-size="11" fill="#5b6b7a">' + gv + "</text>";
    }
    var bars = "";
    series.forEach(function (s, idx) {
      var v = Number(s.cantidad || s.value || 0);
      var bh = (v / max) * innerH;
      var x = padL + idx * (innerW / n) + gap / 2;
      var y = padT + innerH - bh;
      var label = s.label || s.mes || "";
      bars += '<g class="dash-bar" data-label="' + String(label).replace(/"/g, "") + '" data-value="' + v + '">';
      bars += '<rect x="' + x + '" y="' + y + '" width="' + barW + '" height="' + Math.max(bh, 0) +
        '" rx="4" fill="#05294B"/>';
      bars += '<text x="' + (x + barW / 2) + '" y="' + (h - 14) +
        '" text-anchor="middle" font-size="10" fill="#5b6b7a">' + String(label).replace(/</g, "") + "</text>";
      bars += "</g>";
    });
    el.innerHTML = '<svg viewBox="0 0 ' + w + " " + h + '" width="100%" height="' + h +
      '" xmlns="http://www.w3.org/2000/svg">' + grid + bars + "</svg>" +
      '<div class="dash-tooltip d-none" id="dashTip"></div>';

    var tip = el.querySelector("#dashTip");
    Array.prototype.forEach.call(el.querySelectorAll(".dash-bar"), function (g) {
      var rect = g.querySelector("rect");
      g.addEventListener("mouseenter", function () {
        rect.setAttribute("fill", "#FEC001");
        tip.textContent = g.getAttribute("data-label") + ": " + g.getAttribute("data-value") + " operaciones";
        tip.classList.remove("d-none");
      });
      g.addEventListener("mouseleave", function () {
        rect.setAttribute("fill", "#05294B");
        tip.classList.add("d-none");
      });
    });
  };
})();
