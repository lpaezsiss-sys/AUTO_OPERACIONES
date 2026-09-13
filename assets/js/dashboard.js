(function () {
  "use strict";

  function paint(dash) {
    dash = dash || window.COMEX_DASH || {};
    var chart = document.getElementById("chartVolumen");
    if (chart && typeof window.crmBarChart === "function") {
      window.crmBarChart(chart, dash.volumen_mensual || []);
    }
  }

  paint(window.COMEX_DASH);
  crmApi("api/dashboard.php").then(function (dash) {
    window.COMEX_DASH = dash;
    paint(dash);
  }).catch(function () {
    paint(window.COMEX_DASH);
  });
})();
