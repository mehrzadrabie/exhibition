(function () {
  'use strict';
  var D = JSON.parse(document.getElementById('pageData').textContent);
  var info = document.getElementById('info');
  var input = document.getElementById('seatsInput');
  var data = {}, map;
  var COL = { 0: '#94a3b8', 1: '#f59e0b', 2: '#2563eb', 3: '#7c3aed' };

  function color(s) {
    var d = data[s.id];
    if (!d) return COL[0];
    if (d[0] === 2 && d[4]) return '#15803d';
    return COL[d[0]] || COL[0];
  }
  function stats() {
    var c = { 1: 0, 2: 0, 3: 0, in: 0 };
    for (var k in data) { c[data[k][0]]++; if (data[k][4]) c.in++; }
    document.getElementById('stats').innerHTML = 'فروخته: ' + faNum(c[2]) + ' · حاضر: ' + faNum(c.in) + ' · رزرو موقت: ' + faNum(c[1]) + ' · مسدود: ' + faNum(c[3]) + ' · کل: ' + faNum(map.seats.length);
  }
  function load() {
    getJSON(D.api + (D.api.indexOf('?') > -1 ? '&' : '?') + '_=' + Date.now(), function (r) {
      data = r.seats || {};
      map.repaintColors();
      stats();
    });
  }
  loadLayout(D.layout, function (L) {
    map = new SeatMap(document.getElementById('map'), {
      layout: L, rowSelect: true, zoomFirst: false,
      colorOf: color,
      selectable: function (s) { var d = data[s.id]; return !d || d[0] === 3; },
      onChange: function (sel) {
        document.getElementById('selCount').textContent = faNum(sel.length);
        if (input) input.value = sel.map(function (s) { return s.id; }).join(',');
      },
      onInfo: function (s) {
        var d = data[s.id];
        if (!d) return;
        var st = { 1: 'رزرو موقت (در حال پرداخت)', 2: 'فروخته‌شده', 3: 'مسدود' }[d[0]];
        info.innerHTML = '<div class="alert info"><b>ردیف ' + faNum(s.row) + ' – صندلی ' + faNum(s.no) + '</b><br>' + st +
          (d[2] ? '<br>' + d[2].replace(/</g, '&lt;') : '') + (d[3] ? '<br><span class="ltr">' + d[3] + '</span>' : '') +
          (d[4] ? '<br>✅ وارد سالن شده' : '') +
          (d[1] ? '<br><a href="' + D.orderUrl + d[1] + '">مشاهده سفارش ' + faNum(d[1]) + '</a>' : '') + '</div>';
      },
      tipExtra: function (s) { var d = data[s.id]; return d && d[2] ? ' – ' + d[2] : ''; }
    });
    load();
    setInterval(function () { if (!document.hidden) load(); }, 15000);
  });
})();
