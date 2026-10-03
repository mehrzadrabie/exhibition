(function () {
  'use strict';
  var D = JSON.parse(document.getElementById('pageData').textContent);
  var input = document.getElementById('seatsInput');
  var data = {}, map;
  loadLayout(D.layout, function (L) {
    map = new SeatMap(document.getElementById('map'), {
      layout: L, zoomFirst: true,
      colorOf: function (s) { var d = data[s.id]; if (d && d[0] === 3) return '#7c3aed'; var c = map ? map.cats[s.cat] : null; return c ? c.color : '#94a3b8'; },
      selectable: function (s) { var d = data[s.id]; return !d || d[0] === 3; },
      onChange: function (sel) {
        document.getElementById('selCount').textContent = faNum(sel.length);
        document.getElementById('selList').textContent = sel.map(function (s) { return 'ر' + faNum(s.row) + '/ص' + faNum(s.no); }).join('، ');
        input.value = sel.map(function (s) { return s.id; }).join(',');
        document.getElementById('issueBtn').disabled = !sel.length;
      }
    });
    getJSON(D.api + (D.api.indexOf('?') > -1 ? '&' : '?') + '_=' + Date.now(), function (r) {
      data = r.seats || {};
      var st = {};
      for (var k in data) if (data[k][0] === 1 || data[k][0] === 2) st[k] = 'u';
      map.repaintColors();
      map.setState(st);
    });
  });
})();
