(function () {
  'use strict';
  var L = JSON.parse(document.getElementById('layoutData').textContent);
  var input = document.getElementById('seatsInput');
  var map = new SeatMap(document.getElementById('map'), {
    layout: L, rowSelect: true, zoomFirst: false,
    selectable: function () { return true; },
    onChange: function (sel) {
      document.getElementById('selCount').textContent = faNum(sel.length);
      input.value = sel.map(function (s) { return s.id; }).join(',');
    }
  });
  var st = {};
  map.seats.forEach(function (s) { if (!s.active) st[s.id] = 'off'; });
  map.setState(st);
  document.getElementById('selAll').addEventListener('click', function () { map.selectAll(true); });
  document.getElementById('selNone').addEventListener('click', function () { map.selectAll(false); });
})();
