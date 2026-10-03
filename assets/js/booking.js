(function () {
  'use strict';
  var D = JSON.parse(document.getElementById('pageData').textContent);
  var host = document.getElementById('map');
  var list = document.getElementById('cartList');
  var totalEl = document.getElementById('cartTotal');
  var btn = document.getElementById('bookBtn');
  var input = document.getElementById('seatsInput');
  var form = document.getElementById('bookForm');
  var mbBtn = document.getElementById('mbBtn');
  var KEY = 'sel_' + D.sid;
  var map, mine = {};
  D.mine.forEach(function (id) { mine[id] = 1; });

  function price(s) { return D.prices[s.cat]; }

  function store(ids) { try { sessionStorage.setItem(KEY, ids.join(',')); } catch (e) {} }

  function update(sel) {
    var total = 0;
    if (!sel.length) {
      list.innerHTML = '<li class="muted">هنوز صندلی‌ای انتخاب نکرده‌اید.</li>';
    } else {
      list.innerHTML = sel.map(function (s) {
        total += price(s) || 0;
        var c = map.cats[s.cat];
        return '<li><span><i class="swatch" style="background:' + (c ? c.color : '#999') + '"></i> ردیف ' + faNum(s.row) + ' – صندلی ' + faNum(s.no) +
          '</span><span>' + faMoney(price(s) || 0) + ' <button type="button" class="x" data-id="' + s.id + '" aria-label="حذف">×</button></span></li>';
      }).join('');
    }
    totalEl.textContent = faMoney(total) + ' تومان';
    document.getElementById('mbCount').textContent = faNum(sel.length) + ' صندلی';
    document.getElementById('mbTotal').textContent = faMoney(total) + ' تومان';
    btn.disabled = mbBtn.disabled = !sel.length || !D.open;
    var ids = sel.map(function (s) { return s.id; });
    input.value = ids.join(',');
    store(ids);
  }

  list.addEventListener('click', function (e) {
    var id = e.target.getAttribute('data-id');
    if (id) { map.select(+id, false); update(map.selected()); }
  });
  mbBtn.addEventListener('click', function () { submit(); });
  form.addEventListener('submit', function (e) { e.preventDefault(); submit(); });

  function submit() {
    if (!map.count()) return;
    if (!D.logged) {
      store(map.selected().map(function (s) { return s.id; }));
      location.href = window.LOGIN_URL;
      return;
    }
    btn.disabled = mbBtn.disabled = true;
    btn.textContent = mbBtn.textContent = 'در حال ثبت…';
    form.submit();
  }

  function legend() {
    var h = '';
    map.L.cats.forEach(function (c) {
      if (D.prices[c[0]] === undefined) return;
      h += '<span><i style="background:' + c[2] + '"></i>' + c[1] + ' – ' + faMoney(D.prices[c[0]]) + ' تومان</span>';
    });
    h += '<span><i style="background:#111"></i>انتخاب شما</span><span><i style="background:#d9dce1"></i>فروخته‌شده</span><span><i style="background:#eceef1;border:1px dashed #c7cbd2"></i>در حال رزرو</span>';
    document.getElementById('legend').innerHTML = h;
  }

  // ---- live availability ----
  // Polls the static file the server rewrites on every change (cheap 304s); every
  // ~60s, or if the file is missing, the PHP endpoint is asked instead.
  var lastT = 0, firstPoll = true, polls = 0, skew = 0, data = null;

  function serverNow() { return Date.now() / 1000 + skew; }

  function apply() {
    if (!data) return;
    var now = serverNow(), st = {};
    data.u.forEach(function (id) { st[id] = 'u'; });
    data.h.forEach(function (x) { if (x[1] >= now && !mine[x[0]] && !st[x[0]]) st[x[0]] = 'h'; });
    map.seats.forEach(function (s) { if (D.prices[s.cat] === undefined && !st[s.id]) st[s.id] = 'np'; });
    var cb = map.opt.onLost;
    if (firstPoll) map.opt.onLost = null; // silently drop restored seats that are gone
    map.setState(st);
    map.opt.onLost = cb;
    firstPoll = false;
  }

  function fetchStatus(url, isApi) {
    var x = new XMLHttpRequest();
    x.open('GET', url);
    if (!isApi) x.setRequestHeader('Cache-Control', 'no-cache');
    x.onload = function () {
      if (x.status !== 200 && x.status !== 304) { if (!isApi) fetchStatus(D.api, true); return; }
      var date = Date.parse(x.getResponseHeader('Date') || '');
      if (date) skew = date / 1000 - Date.now() / 1000;
      try {
        var r = JSON.parse(x.responseText);
        if (r.t >= lastT) { lastT = r.t; data = r; }
      } catch (e) { return; }
      apply();
    };
    x.onerror = function () { if (!isApi) fetchStatus(D.api, true); };
    x.send();
  }

  function poll() {
    polls++;
    if (polls % 12 === 0) fetchStatus(D.api + (D.api.indexOf('?') > -1 ? '&' : '?') + '_=' + Date.now(), true);
    else fetchStatus(D.status, false);
  }

  loadLayout(D.layout, function (L) {
    map = new SeatMap(host, {
      layout: L,
      max: D.max,
      onChange: update,
      onLimit: function () { alert('حداکثر ' + faNum(D.max) + ' صندلی در هر سفارش قابل انتخاب است.'); },
      onLost: function (lost) {
        alert('صندلی ' + lost.map(function (s) { return 'ردیف ' + faNum(s.row) + '/' + faNum(s.no); }).join('، ') + ' همین حالا توسط فرد دیگری رزرو شد.');
      },
      tipExtra: function (s, st) {
        if (st === 'u') return ' (فروخته‌شده)';
        if (st === 'h') return ' (در حال رزرو)';
        var p = price(s);
        return p !== undefined ? ' – ' + faMoney(p) + ' تومان' : '';
      }
    });
    legend();
    // restore selection: own held seats, or what was picked before login
    var pre = D.mine.slice();
    try { var ss = sessionStorage.getItem(KEY); if (ss && !pre.length) pre = ss.split(',').map(Number); } catch (e) {}
    pre.forEach(function (id) { map.select(id, true); });
    poll();
    update(map.selected());
    setInterval(function () { if (!document.hidden) poll(); }, 5000);
    setInterval(apply, 15000); // expire holds locally even when nothing changed
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
  });
})();
