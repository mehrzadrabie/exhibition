/* QR decoding off the main thread */
self.onmessage = function (e) {
  var d = e.data;
  if (d.init) { importScripts(d.init); return; }
  var r = null;
  try { r = self.jsQR(new Uint8ClampedArray(d.buf), d.w, d.h, { inversionAttempts: 'dontInvert' }); } catch (x) {}
  self.postMessage(r ? r.data : null);
};
