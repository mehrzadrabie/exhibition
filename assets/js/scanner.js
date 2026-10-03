(function () {
  'use strict';
  var D = JSON.parse(document.getElementById('pageData').textContent);
  var video = document.getElementById('video');
  var resultEl = document.getElementById('result');
  var sessionEl = document.getElementById('session');
  var logEl = document.getElementById('log');
  var statsEl = document.getElementById('stats');
  var FA = '۰۱۲۳۴۵۶۷۸۹';
  var fa = function (s) { return String(s).replace(/\d/g, function (d) { return FA[d]; }); };
  var esc = function (s) { return String(s || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };

  var stream = null, detector = null, worker = null, busy = false, paused = false, last = { code: '', t: 0 };
  var devices = [], devIdx = -1;
  var canvas = document.createElement('canvas'), ctx = canvas.getContext('2d', { willReadFrequently: true });

  try { var s = localStorage.getItem('chk_session'); if (s && sessionEl.querySelector('option[value="' + s + '"]')) sessionEl.value = s; } catch (e) {}
  sessionEl.addEventListener('change', function () { try { localStorage.setItem('chk_session', sessionEl.value); } catch (e) {} loadStats(); });

  // ---------- audio feedback ----------
  var AC = null;
  function beep(ok) {
    if (!document.getElementById('sound').checked) return;
    try {
      AC = AC || new (window.AudioContext || window.webkitAudioContext)();
      var tones = ok ? [[1046, 0, 0.12]] : [[330, 0, 0.18], [262, 0.22, 0.3]];
      tones.forEach(function (t) {
        var o = AC.createOscillator(), g = AC.createGain();
        o.frequency.value = t[0]; o.type = 'sine';
        g.gain.setValueAtTime(0.25, AC.currentTime + t[1]);
        g.gain.exponentialRampToValueAtTime(0.001, AC.currentTime + t[1] + t[2]);
        o.connect(g); g.connect(AC.destination);
        o.start(AC.currentTime + t[1]); o.stop(AC.currentTime + t[1] + t[2] + 0.02);
      });
    } catch (e) {}
    if (navigator.vibrate) navigator.vibrate(ok ? 80 : [120, 80, 120]);
  }

  // ---------- server ----------
  function submit(code) {
    paused = true;
    var x = new XMLHttpRequest();
    x.open('POST', D.api);
    x.setRequestHeader('Content-Type', 'application/json');
    x.setRequestHeader('X-CSRF', D.csrf);
    x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    x.timeout = 10000;
    x.onload = function () {
      var r;
      try { r = JSON.parse(x.responseText); } catch (e) { r = { ok: false, result: 'error', message: 'پاسخ نامعتبر از سرور (' + x.status + ')' }; }
      if (x.status === 401 || x.status === 419) r = { ok: false, result: 'error', message: 'نشست شما منقضی شده؛ صفحه را دوباره باز کنید.' };
      show(r);
    };
    x.onerror = x.ontimeout = function () { show({ ok: false, result: 'error', message: 'خطای شبکه! اتصال اینترنت را بررسی کنید.' }); };
    x.send(JSON.stringify({ code: code, session: +sessionEl.value }));
  }

  function show(r) {
    var cls = r.ok ? 'ok' : (r.result === 'duplicate' ? 'dup' : 'bad');
    var t = r.ticket;
    var h = '<div class="big">' + (r.ok ? '✔ ' : (r.result === 'duplicate' ? '⚠ ' : '✖ ')) + esc(r.ok ? 'ورود مجاز' : (r.result === 'duplicate' ? 'تکراری' : 'ورود غیرمجاز')) + '</div>';
    if (t) h += '<div class="seat">ردیف ' + fa(t.row) + ' – صندلی ' + fa(t.seat) + '</div><div>' + esc(t.name) + (t.company ? ' – ' + esc(t.company) : '') + '</div><div class="small">' + esc(t.section ? 'بخش ' + t.section : '') + (t.cat ? ' · ' + esc(t.cat) : '') + '</div>';
    h += '<div class="mt">' + esc(r.message) + '</div><div class="small mt" style="opacity:.8">برای ادامه ضربه بزنید</div>';
    resultEl.className = 'scan-result show ' + cls;
    resultEl.innerHTML = h;
    beep(r.ok);
    var li = document.createElement('li');
    li.innerHTML = '<span>' + (r.ok ? '✅' : (r.result === 'duplicate' ? '⚠️' : '❌')) + ' ' + (t ? esc(t.name) + ' – ر' + fa(t.row) + '/ص' + fa(t.seat) : esc(r.message)) + '</span><span class="muted">' + fa(new Date().toTimeString().slice(0, 5)) + '</span>';
    logEl.insertBefore(li, logEl.firstChild);
    while (logEl.children.length > 30) logEl.removeChild(logEl.lastChild);
    clearTimeout(show.tm);
    show.tm = setTimeout(hide, r.ok ? 1600 : 3500);
    if (r.ok) loadStats();
  }
  function hide() { resultEl.className = 'scan-result'; paused = false; }
  resultEl.addEventListener('click', hide);

  function onCode(code) {
    var now = Date.now();
    if (code === last.code && now - last.t < 4000) return;
    last = { code: code, t: now };
    submit(code);
  }

  function loadStats() {
    getJSONp(D.stats + (D.stats.indexOf('?') > -1 ? '&' : '?') + 'session=' + sessionEl.value, function (r) {
      if (r.ok) statsEl.textContent = 'حاضرین: ' + fa(r.inside) + ' از ' + fa(r.total);
    });
  }
  function getJSONp(url, cb) {
    var x = new XMLHttpRequest();
    x.open('GET', url);
    x.setRequestHeader('Accept', 'application/json');
    x.onload = function () { try { cb(JSON.parse(x.responseText)); } catch (e) {} };
    x.send();
  }

  // ---------- camera ----------
  function start() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      document.getElementById('camNote').innerHTML = '<b style="color:var(--bad)">دسترسی به دوربین ممکن نیست. سایت باید با HTTPS باز شود.</b> از ورود دستی کد استفاده کنید.';
      return;
    }
    stop();
    var c = devIdx >= 0 && devices[devIdx] ? { deviceId: { exact: devices[devIdx].deviceId } } : { facingMode: 'environment' };
    c.width = { ideal: 1280 }; c.height = { ideal: 720 };
    navigator.mediaDevices.getUserMedia({ video: c, audio: false }).then(function (s) {
      stream = s;
      video.srcObject = s;
      video.play();
      document.getElementById('startBtn').textContent = 'توقف';
      navigator.mediaDevices.enumerateDevices().then(function (d) { devices = d.filter(function (x) { return x.kind === 'videoinput'; }); });
      setupDecoder();
      requestAnimationFrame(loop);
    }).catch(function (e) {
      document.getElementById('camNote').innerHTML = '<b style="color:var(--bad)">اجازه دسترسی به دوربین داده نشد (' + esc(e.name) + ').</b>';
    });
  }
  function stop() {
    if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
    stream = null;
    document.getElementById('startBtn').textContent = 'شروع اسکن';
  }
  function setupDecoder() {
    if (detector || worker) return;
    if ('BarcodeDetector' in window) {
      try {
        window.BarcodeDetector.getSupportedFormats().then(function (f) {
          if (f.indexOf('qr_code') > -1) detector = new window.BarcodeDetector({ formats: ['qr_code'] });
          else makeWorker();
        }, makeWorker);
        return;
      } catch (e) {}
    }
    makeWorker();
  }
  function makeWorker() {
    if (worker) return;
    worker = new Worker(D.worker);
    worker.postMessage({ init: D.jsqr });
    worker.onmessage = function (e) { busy = false; if (e.data && !paused) onCode(e.data); };
  }

  var lastScan = 0;
  function loop(ts) {
    if (!stream) return;
    requestAnimationFrame(loop);
    if (paused || busy || video.readyState < 2 || ts - lastScan < 110) return;
    lastScan = ts;
    if (detector) {
      busy = true;
      detector.detect(video).then(function (codes) {
        busy = false;
        if (codes.length && !paused) onCode(codes[0].rawValue);
      }, function () { busy = false; });
      return;
    }
    if (!worker) return;
    var vw = video.videoWidth, vh = video.videoHeight;
    if (!vw) return;
    // decode the centre square, downscaled
    var side = Math.min(vw, vh), sz = Math.min(side, 560);
    canvas.width = canvas.height = sz;
    ctx.drawImage(video, (vw - side) / 2, (vh - side) / 2, side, side, 0, 0, sz, sz);
    var img = ctx.getImageData(0, 0, sz, sz);
    busy = true;
    worker.postMessage({ buf: img.data.buffer, w: sz, h: sz }, [img.data.buffer]);
  }

  document.getElementById('startBtn').addEventListener('click', function () { if (stream) stop(); else start(); });
  document.getElementById('switchBtn').addEventListener('click', function () {
    if (!devices.length) return start();
    devIdx = (devIdx + 1) % devices.length;
    start();
  });
  document.getElementById('manual').addEventListener('submit', function (e) {
    e.preventDefault();
    var v = document.getElementById('manualCode');
    if (v.value.trim()) { last = { code: '', t: 0 }; submit(v.value.trim()); v.value = ''; }
  });

  loadStats();
  setInterval(function () { if (!document.hidden) loadStats(); }, 15000);
  start();
})();
