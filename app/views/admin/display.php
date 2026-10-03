<h1>نمایشگر خوش‌آمد</h1>
<p class="muted">با اسکن موفق QR در ورودی، نام مهمان روی مانیتور سالن با پیام خوش‌آمد نمایش داده می‌شود.</p>

<div class="card">
  <h3>راه‌اندازی روی مانیتور</h3>
  <ol>
    <li>یک کامپیوتر، لپ‌تاپ، مینی‌PC یا تلویزیون هوشمند دارای مرورگر کروم را با HDMI به مانیتور وصل کنید و به اینترنت متصل کنید.</li>
    <li>لینک زیر را در کروم باز کنید و یک بار روی صفحه کلیک کنید تا تمام‌صفحه شود و صدا فعال شود.</li>
    <li>تنظیمات خاموشی خودکار صفحه و حالت خواب کامپیوتر را غیرفعال کنید.</li>
  </ol>
  <div class="field"><label>لینک نمایشگر (همه سانس‌ها)</label>
    <input type="text" class="input-ltr mono" value="<?= e($link) ?>" readonly onclick="this.select()"></div>
  <?php foreach ($sessions as $s): ?>
    <div class="small">فقط <?= e($s['title']) ?>: <a class="ltr" href="<?= e($link . '?s=' . $s['id']) ?>" target="_blank"><?= e($link . '?s=' . $s['id']) ?></a></div>
  <?php endforeach ?>
  <div class="row mt">
    <a class="btn" href="<?= e($link) ?>" target="_blank">باز کردن نمایشگر</a>
    <a class="btn ghost" href="<?= e($link . '?demo=1') ?>" target="_blank">پیش‌نمایش با نام‌های نمونه</a>
    <form method="post" action="<?= e(url('/admin/display')) ?>" data-confirm="لینک فعلی از کار می‌افتد. ادامه می‌دهید؟">
      <?= csrf_field() ?><button class="btn ghost" name="action" value="regenerate">ساخت لینک جدید</button>
    </form>
  </div>
  <p class="small muted mt mb0">این لینک بدون رمز باز می‌شود و نام حاضرین را نشان می‌دهد؛ آن را فقط روی کامپیوتر نمایشگر استفاده کنید. اگر لینک به دست کسی دیگر افتاد، لینک جدید بسازید.</p>
</div>

<form method="post" action="<?= e(url('/admin/display')) ?>" class="card">
  <?= csrf_field() ?>
  <h3>متن و نمایش</h3>
  <div class="grid g2">
    <div class="field"><label>تیتر بالای نام</label><input type="text" name="display_heading" value="<?= e(setting('display_heading', 'خوش آمدید')) ?>"></div>
    <div class="field"><label>پیام زیر نام</label><input type="text" name="display_message" value="<?= e(setting('display_message', 'به ' . setting('site_title', 'همایش') . ' خوش آمدید')) ?>"></div>
    <div class="field"><label>مدت نمایش هر نفر (ثانیه)</label><input type="number" name="display_seconds" min="2" max="20" value="<?= e(setting('display_seconds', 6)) ?>">
      <div class="help">در شلوغی ورودی، زمان نمایش خودکار کوتاه‌تر می‌شود تا نمایش از ورود افراد عقب نماند.</div></div>
    <div class="field">
      <label class="check"><input type="checkbox" name="display_show_company" value="1" <?= setting('display_show_company', '1') === '1' ? 'checked' : '' ?>> نمایش نام شرکت</label>
      <label class="check"><input type="checkbox" name="display_show_seat" value="1" <?= setting('display_show_seat', '1') === '1' ? 'checked' : '' ?>> نمایش ردیف و صندلی (راهنمای نشستن)</label>
    </div>
  </div>
  <button class="btn" type="submit">ذخیره</button>
</form>
