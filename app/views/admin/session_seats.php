<div class="row between"><h1 class="mb0">صندلی‌ها: <?= e($s['title']) ?></h1><a class="btn ghost sm" href="<?= e(url('/admin/sessions')) ?>">بازگشت</a></div>
<p class="small muted">روی صندلی‌های خالی بزنید تا انتخاب شوند، سپس آن‌ها را برای VIP/مهمانان «مسدود» کنید (دیگر در سایت فروخته نمی‌شوند و از بخش «صدور بلیط دستی» قابل تخصیص‌اند). با کلیک روی صندلی فروخته‌شده، مشخصات خریدار نمایش داده می‌شود. کلیک روی شماره ردیف کل ردیف را انتخاب می‌کند.</p>
<div class="seat-layout">
  <div>
    <div class="mapbox" id="map"></div>
    <div class="legend">
      <span><i style="background:#94a3b8"></i>خالی</span><span><i style="background:#2563eb"></i>فروخته‌شده</span><span><i style="background:#15803d"></i>وارد سالن شده</span>
      <span><i style="background:#f59e0b"></i>رزرو موقت</span><span><i style="background:#7c3aed"></i>مسدود / VIP</span><span><i style="background:#111"></i>انتخاب‌شده</span>
    </div>
  </div>
  <aside class="card cart">
    <h3>انتخاب: <span id="selCount">۰</span> صندلی</h3>
    <div id="info" class="small"></div>
    <?php if (admin_can('admin')): ?>
    <form method="post" action="<?= e(url('/admin/sessions/' . $s['id'] . '/seats')) ?>" id="actForm">
      <?= csrf_field() ?>
      <input type="hidden" name="seats" id="seatsInput">
      <div class="row mt">
        <button class="btn dark sm" name="action" value="block" type="submit">مسدود کردن</button>
        <button class="btn ghost sm" name="action" value="unblock" type="submit">آزاد کردن</button>
      </div>
    </form>
    <?php endif ?>
    <a class="btn teal block mt" id="issueBtn" href="<?= e(url('/admin/issue', ['session' => $s['id']])) ?>">صدور بلیط برای صندلی‌های مسدود</a>
    <div id="stats" class="small muted mt"></div>
  </aside>
</div>
<script type="application/json" id="pageData"><?= json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
