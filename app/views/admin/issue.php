<h1>صدور بلیط دستی (VIP، مهمان، فروش حضوری)</h1>
<form method="get" action="<?= e(url('/admin/issue')) ?>" class="filters">
  <?php if (!config('pretty_urls', true)): ?><input type="hidden" name="r" value="/admin/issue"><?php endif ?>
  <div class="field"><label>سانس</label><select name="session" onchange="this.form.submit()">
    <?php foreach ($sessions as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $sid == $s['id'] ? 'selected' : '' ?>><?= e($s['title']) ?> – <?= e(jdate('j F', $s['starts_at'])) ?></option><?php endforeach ?></select></div>
</form>
<div class="seat-layout">
  <div>
    <div class="mapbox" id="map"></div>
    <div class="legend"><span><i style="background:#7c3aed"></i>مسدود/رزرو داخلی (قابل تخصیص)</span><span><i style="background:#d9dce1"></i>فروخته یا در حال خرید</span><span><i style="background:#111"></i>انتخاب‌شده</span></div>
  </div>
  <form class="card cart" method="post" action="<?= e(url('/admin/issue')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="session_id" value="<?= (int)$sid ?>">
    <input type="hidden" name="seats" id="seatsInput">
    <h3><span id="selCount">۰</span> صندلی</h3>
    <div class="small muted" id="selList"></div>
    <div class="field mt"><label>موبایل گیرنده *</label><input type="tel" name="mobile" class="input-ltr" required placeholder="09..."></div>
    <div class="grid g2">
      <div class="field"><label>نام</label><input type="text" name="first_name"></div>
      <div class="field"><label>نام خانوادگی</label><input type="text" name="last_name"></div>
    </div>
    <div class="field"><label>شرکت / سازمان</label><input type="text" name="company"></div>
    <div class="field"><label>مبلغ دریافتی (تومان) – برای مهمان ۰</label><input type="text" name="amount" value="0" class="input-ltr" inputmode="numeric"></div>
    <div class="field"><label>یادداشت</label><input type="text" name="note" placeholder="مثلاً مهمان VIP / پرداخت نقدی"></div>
    <label class="check"><input type="checkbox" name="send_sms" value="1" checked> ارسال پیامک بلیط</label>
    <button class="btn block mt" type="submit" id="issueBtn" disabled>صدور بلیط</button>
  </form>
</div>
<script type="application/json" id="pageData"><?= json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
