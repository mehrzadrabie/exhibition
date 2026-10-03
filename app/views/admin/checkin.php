<div class="scan-wrap">
  <div class="row between">
    <h1 class="mb0">اسکن ورود</h1>
    <span class="badge ok" id="stats">—</span>
  </div>
  <div class="field mt">
    <select id="session">
      <?php foreach ($sessions as $s): ?>
        <option value="<?= (int)$s['id'] ?>" <?= $current === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['title']) ?> – <?= e(jdate('l j F H:i', $s['starts_at'])) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="scan-video" id="vbox">
    <video id="video" playsinline muted></video>
    <div class="scan-frame"></div>
    <div class="scan-result" id="result"></div>
  </div>
  <div class="row mt">
    <button class="btn" type="button" id="startBtn">شروع اسکن</button>
    <button class="btn ghost" type="button" id="switchBtn">تعویض دوربین</button>
    <label class="check small"><input type="checkbox" id="sound" checked> صدا</label>
  </div>
  <form class="row mt" id="manual" style="flex-wrap:nowrap">
    <input type="text" id="manualCode" class="input-ltr mono" placeholder="کد ۱۰ رقمی بلیط" maxlength="40" autocomplete="off">
    <button class="btn dark" type="submit">ثبت دستی</button>
  </form>
  <p class="small muted" id="camNote">اسکنر از دوربین گوشی استفاده می‌کند (نیازمند HTTPS). اسکنر بارکد USB هم کار می‌کند: کافی است فیلد بالا فوکوس داشته باشد.</p>
  <div class="card mt">
    <h3>آخرین اسکن‌ها</h3>
    <ul class="scan-log" id="log"></ul>
  </div>
</div>
<script type="application/json" id="pageData"><?= json_encode(['api' => $api, 'stats' => $statsApi, 'csrf' => csrf_token(), 'worker' => asset('js/qr-worker.js'), 'jsqr' => asset('vendor/jsQR.min.js')], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
