(function () {
  'use strict';
  var C = JSON.parse(document.getElementById('cfg').textContent);
  var FA = '۰۱۲۳۴۵۶۷۸۹';
  var fa = function (s) { return String(s).replace(/\d/g, function (d) { return FA[d]; }); };
  var $ = function (id) { return document.getElementById(id); };
  var queue = [], showing = false, last = 0, recent = [], audio = null;

  // ---------- clock ----------
  function clock() {
    var d = new Date();
    $('clock').textContent = fa(('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2));
  }
  clock(); setInterval(clock, 10000);

  // ---------- chime ----------
  function chime() {
    if (!audio) return;
    try {
      [[784, 0], [1046, 0.14]].forEach(function (t) {
        var o = audio.createOscillator(), g = audio.createGain();
        o.type = 'sine'; o.frequency.value = t[0];
        g.gain.setValueAtTime(0.0001, audio.currentTime + t[1]);
        g.gain.exponentialRampToValueAtTime(0.2, audio.currentTime + t[1] + 0.03);
        g.gain.exponentialRampToValueAtTime(0.0001, audio.currentTime + t[1] + 0.6);
        o.connect(g); g.connect(audio.destination);
        o.start(audio.currentTime + t[1]); o.stop(audio.currentTime + t[1] + 0.65);
      });
    } catch (e) {}
  }

  // ---------- show queue ----------
  function next() {
    if (!queue.length) {
      showing = false;
      $('welcome').classList.remove('show');
      $('idle').classList.add('show');
      return;
    }
    showing = true;
    var p = queue.shift();
    // with a crowd at the door, move faster so the screen keeps up
    var secs = queue.length > 4 ? Math.max(2, C.seconds / 3) : queue.length > 1 ? Math.max(2.5, C.seconds / 2) : C.seconds;
    $('wHeading').textContent = C.heading;
    $('wName').textContent = p.name;
    $('wCompany').textContent = C.company && p.company ? p.company : '';
    $('wSeat').textContent = C.seat && p.row ? 'ردیف ' + fa(p.row) + ' – صندلی ' + fa(p.seat) : '';
    $('wMsg').textContent = C.message;
    var w = $('welcome');
    w.classList.remove('show'); void w.offsetWidth; // restart animation
    $('idle').classList.remove('show');
    w.classList.add('show');
    var t = $('wTimer');
    t.style.transition = 'none'; t.style.transform = 'scaleX(1)'; void t.offsetWidth;
    t.style.transition = 'transform ' + secs + 's linear'; t.style.transform = 'scaleX(0)';
    chime();
    addRecent(p.name);
    setTimeout(next, secs * 1000);
  }

  function addRecent(name) {
    recent.unshift(name);
    recent = recent.slice(0, 6);
    $('recent').innerHTML = recent.slice(1).map(function (n) {
      return '<span>' + n.replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }) + '</span>';
    }).join('');
  }

  function push(items) {
    items.forEach(function (it) { if (it.name) queue.push(it); });
    if (queue.length > 40) queue = queue.slice(-40);
    if (!showing && queue.length) next();
  }

  // ---------- polling ----------
  var delay = 2000;
  function poll() {
    var x = new XMLHttpRequest();
    x.open('GET', C.feed + (C.feed.indexOf('?') > -1 ? '&' : '?') + 'after=' + last + '&_=' + Date.now());
    x.timeout = 8000;
    x.onload = function () {
      try {
        var r = JSON.parse(x.responseText);
        if (r.ok) {
          if (last && r.items) push(r.items);
          last = r.last || last;
          if (r.inside !== undefined) $('inside').textContent = 'حاضرین: ' + fa(r.inside) + ' نفر';
          $('net').className = 'net';
          delay = 2000;
        }
      } catch (e) { fail(); }
      setTimeout(poll, delay);
    };
    x.onerror = x.ontimeout = function () { fail(); setTimeout(poll, delay); };
    x.send();
  }
  function fail() { $('net').className = 'net off'; delay = Math.min(delay * 2, 15000); }

  // ---------- start: fullscreen, audio, keep screen awake ----------
  $('start').addEventListener('click', function () {
    $('start').classList.add('hide');
    try { audio = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) {}
    var el = document.documentElement;
    if (el.requestFullscreen) el.requestFullscreen().catch(function () {});
    wake();
  });
  function wake() {
    if ('wakeLock' in navigator) navigator.wakeLock.request('screen').catch(function () {});
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) wake(); });

  var mt;
  document.addEventListener('mousemove', function () {
    document.body.classList.remove('nocursor');
    clearTimeout(mt); mt = setTimeout(function () { document.body.classList.add('nocursor'); }, 3000);
  });

  // reload every 6 hours to pick up settings changes and free memory
  setTimeout(function () { location.reload(); }, 6 * 3600 * 1000);

  if (C.demo) {
    var names = [['مهرزاد ربیعی', 'Vcandoo'], ['سارا احمدی', 'گروه صنعتی کرمان'], ['علی رضایی', ''], ['مریم کریمی', 'شرکت مس'], ['حسین محمدی', 'پسته رفسنجان']];
    var i = 0;
    last = 1;
    setInterval(function () { var n = names[i++ % names.length]; push([{ name: n[0], company: n[1], row: 3 + i % 15, seat: 1 + (i * 7) % 20 }]); }, 4500);
    push([{ name: names[0][0], company: names[0][1], row: 10, seat: 15 }]);
  } else {
    poll();
  }
})();
