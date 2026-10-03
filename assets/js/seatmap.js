/* Lightweight SVG seat map with zoom / pan / pinch. No dependencies. */
(function (w) {
  'use strict';
  var NS = 'http://www.w3.org/2000/svg';
  var FA = '۰۱۲۳۴۵۶۷۸۹';
  function fa(n) { return String(n).replace(/\d/g, function (d) { return FA[d]; }); }
  w.faNum = fa;
  w.faMoney = function (n) { return fa(Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '٬')); };

  function SeatMap(host, opt) {
    this.host = host;
    this.opt = opt || {};
    this.L = opt.layout;
    this.seats = [];
    this.byId = {};
    this.sel = {};
    this.state = {};
    this.cats = {};
    var i;
    for (i = 0; i < this.L.cats.length; i++) this.cats[this.L.cats[i][0]] = { id: this.L.cats[i][0], name: this.L.cats[i][1], color: this.L.cats[i][2] };
    for (i = 0; i < this.L.seats.length; i++) {
      var a = this.L.seats[i];
      var s = { i: i, id: a[0], row: a[1], no: a[2], sec: a[3], x: a[4], y: a[5], rot: a[6], cat: a[7], active: a.length > 8 ? a[8] : 1 };
      this.seats.push(s);
      this.byId[s.id] = s;
    }
    this.build();
  }

  SeatMap.prototype.build = function () {
    var self = this, L = this.L, b = L.box;
    this.full = { x: b[0], y: b[1], w: b[2], h: b[3] };
    this.vb = { x: b[0], y: b[1], w: b[2], h: b[3] };
    var parts = [];
    // stage
    var r = L.stage.r, a = L.stage.a;
    var x1 = (-r * Math.sin(a)).toFixed(1), x2 = (r * Math.sin(a)).toFixed(1), ya = (-r * Math.cos(a)).toFixed(1), yb = (-r + 110).toFixed(1);
    parts.push('<path d="M' + x1 + ' ' + ya + ' A' + r + ' ' + r + ' 0 0 1 ' + x2 + ' ' + ya + ' L' + (x2 * 0.8).toFixed(1) + ' ' + yb + ' L' + (x1 * 0.8).toFixed(1) + ' ' + yb + 'Z" fill="#fdf0f0" stroke="#e31e24" stroke-width="1.5"/>');
    parts.push('<text class="stage-txt" x="0" y="' + (-r + 62) + '">صحنه</text>');
    // row labels
    if (this.opt.labels !== false) {
      for (var k = 0; k < L.labels.length; k++) {
        var lb = L.labels[k];
        parts.push('<g class="rowlbl-g" data-row="' + lb[0] + '"' + (this.opt.rowSelect ? ' style="cursor:pointer"' : '') + '><circle class="rowlbl-c" cx="' + lb[1] + '" cy="' + lb[2] + '" r="10"/><text class="rowlbl" x="' + lb[1] + '" y="' + lb[2] + '">' + fa(lb[0]) + '</text></g>');
      }
    }
    for (var i = 0; i < this.seats.length; i++) {
      var s = this.seats[i];
      parts.push('<g class="st" data-i="' + i + '" transform="translate(' + s.x + ' ' + s.y + ') rotate(' + s.rot + ')"><rect x="-11" y="-10" width="22" height="20" rx="4" fill="' + this.colorOf(s) + '"/><text>' + fa(s.no) + '</text></g>');
    }
    var svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('xmlns', NS);
    svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    svg.innerHTML = parts.join('');
    this.svg = svg;
    this.applyVB();
    this.host.appendChild(svg);
    var nodes = svg.querySelectorAll('g.st');
    for (i = 0; i < nodes.length; i++) this.seats[i].el = nodes[i];

    // zoom buttons
    var tools = document.createElement('div');
    tools.className = 'map-tools';
    tools.innerHTML = '<button type="button" data-z="in" aria-label="بزرگ‌نمایی">+</button><button type="button" data-z="out" aria-label="کوچک‌نمایی">−</button><button type="button" data-z="reset" aria-label="نمای کامل">⤢</button>';
    this.host.appendChild(tools);
    tools.addEventListener('click', function (e) {
      var z = e.target.getAttribute('data-z');
      if (z === 'in') self.zoomAt(1.5);
      else if (z === 'out') self.zoomAt(1 / 1.5);
      else if (z === 'reset') { self.vb = { x: self.full.x, y: self.full.y, w: self.full.w, h: self.full.h }; self.applyVB(); }
    });

    this.tip = document.createElement('div');
    this.tip.className = 'tip';
    document.body.appendChild(this.tip);
    this.bindPointer();
    this.refreshAll();
  };

  SeatMap.prototype.colorOf = function (s) {
    if (this.opt.colorOf) return this.opt.colorOf(s);
    var c = this.cats[s.cat];
    return c ? c.color : '#94a3b8';
  };

  SeatMap.prototype.applyVB = function () {
    var v = this.vb;
    this.svg.setAttribute('viewBox', v.x.toFixed(1) + ' ' + v.y.toFixed(1) + ' ' + v.w.toFixed(1) + ' ' + v.h.toFixed(1));
    // at full view let the page scroll vertically; once zoomed the map owns the gestures
    this.svg.style.touchAction = v.w >= this.full.w - 0.5 ? 'pan-y' : 'none';
  };

  SeatMap.prototype.clampVB = function () {
    var f = this.full, v = this.vb;
    var minW = f.w / 8;
    if (v.w > f.w) { v.h = v.h * f.w / v.w; v.w = f.w; }
    if (v.w < minW) { v.h = v.h * minW / v.w; v.w = minW; }
    v.x = Math.min(Math.max(v.x, f.x - v.w * 0.25), f.x + f.w - v.w * 0.75);
    v.y = Math.min(Math.max(v.y, f.y - v.h * 0.25), f.y + f.h - v.h * 0.75);
  };

  /** zoom by factor around svg point (cx,cy) or centre */
  SeatMap.prototype.zoomAt = function (factor, cx, cy) {
    var v = this.vb;
    if (cx === undefined) { cx = v.x + v.w / 2; cy = v.y + v.h / 2; }
    v.x = cx - (cx - v.x) / factor;
    v.y = cy - (cy - v.y) / factor;
    v.w /= factor; v.h /= factor;
    this.clampVB();
    this.applyVB();
  };

  SeatMap.prototype.toSvg = function (clientX, clientY) {
    var r = this.svg.getBoundingClientRect();
    var v = this.vb;
    var scale = Math.max(v.w / r.width, v.h / r.height);
    var ox = (r.width - v.w / scale) / 2, oy = (r.height - v.h / scale) / 2;
    return { x: v.x + (clientX - r.left - ox) * scale, y: v.y + (clientY - r.top - oy) * scale, s: scale };
  };

  SeatMap.prototype.bindPointer = function () {
    var self = this, svg = this.svg;
    var ptrs = {}, start = null, moved = false, pinch = null;
    svg.addEventListener('pointerdown', function (e) {
      ptrs[e.pointerId] = { x: e.clientX, y: e.clientY };
      var ids = Object.keys(ptrs);
      if (ids.length === 1) {
        start = { x: e.clientX, y: e.clientY, vx: self.vb.x, vy: self.vb.y, target: e.target };
        moved = false;
      } else if (ids.length === 2) {
        var p1 = ptrs[ids[0]], p2 = ptrs[ids[1]];
        pinch = { d: Math.hypot(p1.x - p2.x, p1.y - p2.y), w: self.vb.w, h: self.vb.h, c: self.toSvg((p1.x + p2.x) / 2, (p1.y + p2.y) / 2) };
        moved = true;
      }
      try { svg.setPointerCapture(e.pointerId); } catch (x) {}
    });
    svg.addEventListener('pointermove', function (e) {
      if (!ptrs[e.pointerId]) { self.hover(e); return; }
      ptrs[e.pointerId] = { x: e.clientX, y: e.clientY };
      var ids = Object.keys(ptrs);
      if (ids.length === 2 && pinch) {
        var p1 = ptrs[ids[0]], p2 = ptrs[ids[1]];
        var d = Math.hypot(p1.x - p2.x, p1.y - p2.y);
        var f = d / pinch.d;
        var v = self.vb;
        var nw = pinch.w / f, nh = pinch.h / f;
        v.x = pinch.c.x - (pinch.c.x - v.x) * nw / v.w;
        v.y = pinch.c.y - (pinch.c.y - v.y) * nh / v.h;
        v.w = nw; v.h = nh;
        self.clampVB(); self.applyVB();
      } else if (ids.length === 1 && start) {
        var dx = e.clientX - start.x, dy = e.clientY - start.y;
        if (!moved && Math.abs(dx) + Math.abs(dy) < 7) return;
        moved = true;
        if (self.vb.w >= self.full.w - 0.5) return; // nothing to pan at full view
        var sc = self.toSvg(0, 0).s;
        self.vb.x = start.vx - dx * sc;
        self.vb.y = start.vy - dy * sc;
        self.clampVB(); self.applyVB();
      }
    });
    var end = function (e) {
      var wasSingle = Object.keys(ptrs).length === 1;
      delete ptrs[e.pointerId];
      if (Object.keys(ptrs).length < 2) pinch = null;
      if (e.type === 'pointerup' && wasSingle && !moved && start) self.tap(start.target, e);
      if (!Object.keys(ptrs).length) start = null;
    };
    svg.addEventListener('pointerup', end);
    svg.addEventListener('pointercancel', end);
    svg.addEventListener('pointerleave', function () { self.tip.style.display = 'none'; });
    svg.addEventListener('wheel', function (e) {
      if (!e.ctrlKey && self.vb.w >= self.full.w - 0.5 && e.deltaY > 0) return;
      e.preventDefault();
      var p = self.toSvg(e.clientX, e.clientY);
      self.zoomAt(e.deltaY < 0 ? 1.2 : 1 / 1.2, p.x, p.y);
    }, { passive: false });
    svg.addEventListener('dblclick', function (e) {
      var p = self.toSvg(e.clientX, e.clientY);
      self.zoomAt(2, p.x, p.y);
    });
  };

  SeatMap.prototype.seatFromTarget = function (t) {
    while (t && t !== this.svg) {
      if (t.getAttribute && t.getAttribute('data-i') !== null) return this.seats[+t.getAttribute('data-i')];
      t = t.parentNode;
    }
    return null;
  };

  SeatMap.prototype.tap = function (target, e) {
    var s = this.seatFromTarget(target);
    if (!s) {
      var g = target.closest ? target.closest('.rowlbl-g') : null;
      if (g && this.opt.rowSelect) this.toggleRow(+g.getAttribute('data-row'));
      return;
    }
    // on small screens zoom in first when the map is fully zoomed out
    if (this.opt.zoomFirst !== false && this.vb.w >= this.full.w - 0.5 && this.svg.getBoundingClientRect().width < 560) {
      this.zoomAt(2.6, s.x, s.y);
      return;
    }
    this.toggle(s);
  };

  SeatMap.prototype.canSelect = function (s) {
    if (this.opt.readonly) return false;
    if (this.opt.selectable) return this.opt.selectable(s, this.state[s.id] || '');
    return !this.state[s.id];
  };

  SeatMap.prototype.toggle = function (s) {
    if (this.sel[s.id]) {
      delete this.sel[s.id];
    } else {
      if (!this.canSelect(s)) { if (this.opt.onInfo) this.opt.onInfo(s, this.state[s.id] || ''); return; }
      if (this.opt.max && this.count() >= this.opt.max) { if (this.opt.onLimit) this.opt.onLimit(); return; }
      this.sel[s.id] = true;
    }
    this.paint(s);
    if (this.opt.onChange) this.opt.onChange(this.selected());
  };

  SeatMap.prototype.toggleRow = function (row) {
    var list = this.seats.filter(function (s) { return s.row === row; });
    var self = this;
    var all = list.every(function (s) { return self.sel[s.id]; });
    list.forEach(function (s) {
      if (all) delete self.sel[s.id];
      else if (self.canSelect(s)) self.sel[s.id] = true;
      self.paint(s);
    });
    if (this.opt.onChange) this.opt.onChange(this.selected());
  };

  SeatMap.prototype.selectAll = function (on) {
    var self = this;
    this.seats.forEach(function (s) {
      if (on && self.canSelect(s)) self.sel[s.id] = true; else delete self.sel[s.id];
      self.paint(s);
    });
    if (this.opt.onChange) this.opt.onChange(this.selected());
  };

  SeatMap.prototype.count = function () { return Object.keys(this.sel).length; };

  SeatMap.prototype.selected = function () {
    var self = this;
    return Object.keys(this.sel).map(function (id) { return self.byId[id]; }).filter(Boolean)
      .sort(function (a, b) { return a.row - b.row || a.no - b.no; });
  };

  SeatMap.prototype.select = function (id, on) {
    var s = this.byId[id];
    if (!s) return;
    if (on) this.sel[id] = true; else delete this.sel[id];
    this.paint(s);
  };

  /** state: {seatId: 'u'|'h'|'off'|'np'|custom} */
  SeatMap.prototype.setState = function (state) {
    this.state = state || {};
    var lost = [];
    for (var id in this.sel) {
      if (this.state[id] && !this.canSelect(this.byId[id])) { lost.push(this.byId[id]); delete this.sel[id]; }
    }
    this.refreshAll();
    if (lost.length && this.opt.onLost) this.opt.onLost(lost);
    if (lost.length && this.opt.onChange) this.opt.onChange(this.selected());
  };

  SeatMap.prototype.refreshAll = function () {
    for (var i = 0; i < this.seats.length; i++) this.paint(this.seats[i]);
  };

  SeatMap.prototype.repaintColors = function () {
    for (var i = 0; i < this.seats.length; i++) this.seats[i].el.firstChild.setAttribute('fill', this.colorOf(this.seats[i]));
    this.refreshAll();
  };

  SeatMap.prototype.paint = function (s) {
    var c = 'st';
    var st = this.state[s.id];
    if (st) c += ' ' + st;
    if (this.sel[s.id]) c += ' sel';
    if (this.opt.highlight === s.id) c += ' sel';
    if (s.el.getAttribute('class') !== c) s.el.setAttribute('class', c);
  };

  SeatMap.prototype.hover = function (e) {
    var s = this.seatFromTarget(e.target);
    if (!s || e.pointerType === 'touch') { this.tip.style.display = 'none'; return; }
    var txt = 'ردیف ' + fa(s.row) + ' – صندلی ' + fa(s.no);
    if (this.opt.tipExtra) txt += this.opt.tipExtra(s, this.state[s.id] || '');
    this.tip.textContent = txt;
    this.tip.style.display = 'block';
    this.tip.style.left = (e.clientX + 12) + 'px';
    this.tip.style.top = (e.clientY + 14) + 'px';
  };

  SeatMap.prototype.focusSeat = function (id, factor) {
    var s = this.byId[id];
    if (!s) return;
    this.vb = { x: this.full.x, y: this.full.y, w: this.full.w, h: this.full.h };
    this.zoomAt(factor || 2.2, s.x, s.y);
    var v = this.vb;
    v.x = s.x - v.w / 2; v.y = s.y - v.h / 2;
    this.clampVB(); this.applyVB();
  };

  w.SeatMap = SeatMap;

  w.loadLayout = function (url, cb) {
    var x = new XMLHttpRequest();
    x.open('GET', url);
    x.onload = function () { cb(JSON.parse(x.responseText)); };
    x.send();
  };

  w.getJSON = function (url, cb, err) {
    var x = new XMLHttpRequest();
    x.open('GET', url);
    x.setRequestHeader('Accept', 'application/json');
    x.onload = function () { try { cb(JSON.parse(x.responseText)); } catch (e) { if (err) err(e); } };
    x.onerror = function () { if (err) err(); };
    x.send();
  };
})(window);
