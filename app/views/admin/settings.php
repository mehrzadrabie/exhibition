<h1>تنظیمات</h1>
<form method="post" action="<?= e(url('/admin/settings')) ?>">
  <?= csrf_field() ?>
  <?php foreach ($fields as $group => $fs): ?>
  <div class="card">
    <h3><?= e($group) ?></h3>
    <div class="grid g2">
    <?php foreach ($fs as $k => $f): $v = isset($values[$k]) ? $values[$k] : ''; ?>
      <div class="field" <?= $f[1] === 'textarea' ? 'style="grid-column:1/-1"' : '' ?>>
        <label><?= e($f[0]) ?></label>
        <?php if ($f[1] === 'textarea'): ?><textarea name="<?= e($k) ?>"><?= e($v) ?></textarea>
        <?php elseif ($f[1] === 'select'): ?><select name="<?= e($k) ?>"><?php foreach ($f[2] as $ok => $ol): ?><option value="<?= e($ok) ?>" <?= (string)$v === (string)$ok ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach ?></select>
        <?php elseif ($f[1] === 'password'): ?><input type="password" name="<?= e($k) ?>" class="input-ltr" placeholder="<?= $v !== '' ? '•••••• (برای تغییر وارد کنید)' : '' ?>" autocomplete="new-password">
        <?php else: ?><input type="<?= $f[1] === 'number' ? 'number' : 'text' ?>" name="<?= e($k) ?>" value="<?= e($v) ?>" <?= strpos($k, 'sms_') === 0 || strpos($k, 'merchant') !== false ? 'class="input-ltr"' : '' ?>><?php endif ?>
      </div>
    <?php endforeach ?>
    </div>
  </div>
  <?php endforeach ?>
  <button class="btn lg" type="submit">ذخیره تنظیمات</button>
</form>
<div class="card mt">
  <h3>ارسال پیامک آزمایشی</h3>
  <form method="post" action="<?= e(url('/admin/settings/test-sms')) ?>" class="row" style="flex-wrap:nowrap">
    <?= csrf_field() ?><input type="tel" name="mobile" class="input-ltr" placeholder="09..."><button class="btn ghost" type="submit">ارسال</button>
  </form>
  <p class="small muted mt mb0">آدرس بازگشت درگاه (Callback): <span class="ltr mono"><?= e(abs_url('/payment/callback')) ?></span></p>
</div>
