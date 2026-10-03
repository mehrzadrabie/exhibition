(function () {
  'use strict';
  var D = JSON.parse(document.getElementById('ticketData').textContent);
  var box = document.getElementById('qr');
  var text = box.getAttribute('data-text');

  var qr = qrcode(0, 'M');
  qr.addData(text);
  qr.make();
  var n = qr.getModuleCount();

  function drawQR(ctx, x, y, size) {
    var cell = size / (n + 8), off = cell * 4;
    ctx.fillStyle = '#fff';
    ctx.fillRect(x, y, size, size);
    ctx.fillStyle = '#000';
    for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) {
      if (qr.isDark(r, c)) ctx.fillRect(Math.floor(x + off + c * cell), Math.floor(y + off + r * cell), Math.ceil(cell), Math.ceil(cell));
    }
  }

  var cv = document.createElement('canvas');
  cv.width = cv.height = 440;
  drawQR(cv.getContext('2d'), 0, 0, 440);
  box.appendChild(cv);

  // mini map with the seat highlighted
  var mm = document.getElementById('miniMap');
  if (mm && window.loadLayout) {
    loadLayout(D.layout, function (L) {
      var m = new SeatMap(mm, { layout: L, readonly: true, highlight: D.seat, zoomFirst: false });
      m.focusSeat(D.seat, 2);
    });
  }

  // downloadable ticket image
  document.getElementById('dlBtn').addEventListener('click', function () {
    var W = 720, H = 1180;
    var c = document.createElement('canvas');
    c.width = W; c.height = H;
    var g = c.getContext('2d');
    var go = function () {
      g.fillStyle = '#fff'; g.fillRect(0, 0, W, H);
      var grd = g.createLinearGradient(0, 0, W, 260);
      grd.addColorStop(0, '#16181d'); grd.addColorStop(1, '#3a1416');
      g.fillStyle = grd; g.fillRect(0, 0, W, 260);
      g.direction = 'rtl'; g.textAlign = 'right';
      var F = function (sz, w) { return (w || 'bold') + ' ' + sz + 'px Vazirmatn, Tahoma, sans-serif'; };
      g.fillStyle = 'rgba(255,255,255,.8)'; g.font = F(24, 'normal'); g.fillText(D.site, W - 40, 60);
      g.fillStyle = '#fff'; g.font = F(36); g.fillText(D.title, W - 40, 115);
      g.fillStyle = 'rgba(255,255,255,.85)'; g.font = F(24, 'normal'); g.fillText(D.date, W - 40, 170);
      g.fillText(D.venue || '', W - 40, 215);
      g.textAlign = 'center'; g.fillStyle = '#6b7280'; g.font = F(24, 'normal');
      var cols = [[W * 0.8, 'ردیف', D.row], [W * 0.5, 'صندلی', D.no], [W * 0.2, 'بخش', D.section]];
      cols.forEach(function (k) { g.fillStyle = '#6b7280'; g.font = F(24, 'normal'); g.fillText(k[1], k[0], 315); g.fillStyle = '#16181d'; g.font = F(52); g.fillText(k[2], k[0], 380); });
      g.textAlign = 'right'; g.fillStyle = '#16181d'; g.font = F(28);
      g.fillText('به نام: ' + D.holder, W - 40, 450);
      if (D.cat) { g.font = F(24, 'normal'); g.fillStyle = '#3d434d'; g.fillText('جایگاه: ' + D.cat, W - 40, 495); }
      g.setLineDash([10, 8]); g.strokeStyle = '#d1d5db'; g.lineWidth = 2; g.beginPath(); g.moveTo(30, 530); g.lineTo(W - 30, 530); g.stroke(); g.setLineDash([]);
      drawQR(g, (W - 520) / 2, 560, 520);
      g.textAlign = 'center'; g.direction = 'ltr'; g.fillStyle = '#16181d'; g.font = 'bold 34px ui-monospace, Consolas, monospace';
      g.fillText(D.code.split('').join(' '), W / 2, 1125);
      var a = document.createElement('a');
      a.download = 'ticket-' + D.code + '.png';
      a.href = c.toDataURL('image/png');
      document.body.appendChild(a); a.click(); a.remove();
    };
    if (document.fonts && document.fonts.load) document.fonts.load('bold 20px Vazirmatn').then(go, go); else go();
  });
})();
