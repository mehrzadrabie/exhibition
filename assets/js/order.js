(function () {
  var el = document.getElementById('countdown');
  if (el) {
    var FA = '۰۱۲۳۴۵۶۷۸۹';
    var fa = function (s) { return String(s).replace(/\d/g, function (d) { return FA[d]; }); };
    var offset = (+el.getAttribute('data-now')) * 1000 - Date.now();
    var until = (+el.getAttribute('data-until')) * 1000;
    var tick = function () {
      var left = Math.max(0, Math.floor((until - Date.now() - offset) / 1000));
      el.textContent = fa(Math.floor(left / 60) + ':' + ('0' + (left % 60)).slice(-2));
      if (left <= 0) { location.reload(); return; }
      setTimeout(tick, 1000);
    };
    tick();
  }
  var pf = document.getElementById('payForm');
  if (pf) pf.addEventListener('submit', function () {
    var b = pf.querySelector('button');
    setTimeout(function () { b.disabled = true; b.textContent = 'در حال انتقال به درگاه…'; }, 0);
  });
})();
