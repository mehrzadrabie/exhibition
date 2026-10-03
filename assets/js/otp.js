(function () {
  var fa = function (n) { return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
  var code = document.getElementById('code');
  var form = document.getElementById('otpForm');
  if (code && form) {
    var sent = false;
    var send = function () {
      if (sent) return;
      sent = true;
      var b = form.querySelector('button');
      if (b) { b.disabled = true; b.textContent = 'در حال ورود…'; }
      if (typeof ac !== 'undefined' && ac) ac.abort();
      form.submit();
    };
    form.addEventListener('submit', function (e) { e.preventDefault(); send(); });
    code.addEventListener('input', function () {
      var v = code.value.replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/\D/g, '');
      code.value = v;
      if (v.length === (parseInt(code.getAttribute('data-len'), 10) || 5)) send();
    });
    // Web OTP API (Android Chrome) – fills the code automatically when the SMS format allows it
    if ('OTPCredential' in window) {
      var ac = new AbortController();
      navigator.credentials.get({ otp: { transport: ['sms'] }, signal: ac.signal }).then(function (o) {
        if (o && o.code) { code.value = o.code; send(); }
      }).catch(function () {});
      form.addEventListener('submit', function () { ac.abort(); }, true);
    }
  }
  var btn = document.getElementById('resend');
  var lbl = document.getElementById('resendWait');
  if (btn) {
    var w = parseInt(btn.getAttribute('data-wait'), 10) || 0;
    var tick = function () {
      if (w <= 0) { btn.disabled = false; lbl.textContent = ''; return; }
      btn.disabled = true;
      lbl.textContent = '(' + fa(w) + ' ثانیه)';
      w--; setTimeout(tick, 1000);
    };
    tick();
  }
})();
