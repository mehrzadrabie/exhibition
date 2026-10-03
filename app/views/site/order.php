<?php list($stLabel, $stCls) = order_status_label($o['status']); ?>
<div class="row between">
  <h1 class="mb0">سفارش <?= e(fa($o['id'])) ?></h1>
  <span class="badge <?= e($stCls) ?>"><?= e($stLabel) ?></span>
</div>
<p class="muted"><?= e($session['title']) ?> · <?= e(jdate('l j F Y – ساعت H:i', $session['starts_at'])) ?></p>

<?php if ($o['status'] === 'paid'): ?>

  <div class="alert ok">پرداخت تأیید شد<?= $o['ref_id'] ? ' — کد پیگیری: <b class="ltr">' . e($o['ref_id']) . '</b>' : '' ?>. لینک بلیط‌ها برای شما پیامک می‌شود.</div>
  <div class="grid g3">
    <?php foreach ($tickets as $t): $tok = ticket_token($t['code']); ?>
    <div class="card">
      <div class="row between">
        <b><?= e(seat_label($t['row_no'], $t['seat_no'])) ?></b>
        <?php if ($t['status'] !== 'valid'): ?><span class="badge bad">باطل‌شده</span>
        <?php elseif ($t['checked_in_at']): ?><span class="badge muted">وارد شده</span>
        <?php else: ?><span class="badge ok">معتبر</span><?php endif ?>
      </div>
      <div class="small muted">کد بلیط: <span class="mono"><?= e($t['code']) ?></span></div>
      <?php if ($t['guest_name']): ?><div class="small">به نام: <?= e($t['guest_name']) ?></div><?php endif ?>
      <a class="btn block mt" href="<?= e(url('/t/' . $tok)) ?>">نمایش بلیط و QR</a>
    </div>
    <?php endforeach ?>
  </div>

  <?php if (count($tickets) > 0): ?>
  <div class="card mt">
    <h3>ارسال بلیط برای همراهان (اختیاری)</h3>
    <p class="small muted">اگر بلیط‌ها را برای همکاران یا مهمانان خریده‌اید، نام و موبایل هر نفر را وارد کنید تا بلیط اختصاصی‌اش پیامک شود.</p>
    <form method="post" action="<?= e(url('/order/' . $o['id'] . '/guests')) ?>">
      <?= csrf_field() ?>
      <div class="tablewrap">
      <table class="t">
        <tr><th>صندلی</th><th>نام مهمان</th><th>موبایل مهمان</th></tr>
        <?php foreach ($tickets as $t): if ($t['status'] !== 'valid') continue; ?>
        <tr>
          <td class="small"><?= e('ر' . fa($t['row_no']) . ' / ص' . fa($t['seat_no'])) ?></td>
          <td><input type="text" name="guest_name[<?= (int)$t['id'] ?>]" value="<?= e($t['guest_name']) ?>" maxlength="120"></td>
          <td><input type="tel" name="guest_mobile[<?= (int)$t['id'] ?>]" value="<?= e($t['guest_mobile']) ?>" class="input-ltr" placeholder="09..." maxlength="14"></td>
        </tr>
        <?php endforeach ?>
      </table>
      </div>
      <div class="row mt">
        <label class="check"><input type="checkbox" name="send_sms" value="1" checked> ارسال پیامک بلیط برای شماره‌های جدید</label>
        <label class="check"><input type="checkbox" name="resend" value="1"> ارسال مجدد برای همه</label>
      </div>
      <button class="btn dark mt" type="submit">ذخیره</button>
    </form>
  </div>
  <?php endif ?>

<?php else: ?>

  <div class="grid g2">
    <div class="card">
      <h3>صندلی‌ها</h3>
      <div class="tablewrap">
      <table class="t">
        <?php foreach ($items as $it): ?>
        <tr>
          <td><i class="swatch" style="background:<?= e($it['color']) ?>"></i> <?= e(seat_label($it['row_no'], $it['seat_no'])) ?></td>
          <td class="small muted"><?= e($it['cat_name']) ?></td>
          <td><?= money($it['price']) ?></td>
        </tr>
        <?php endforeach ?>
      </table>
      </div>
    </div>
    <div class="card">
      <h3>پرداخت</h3>
      <dl class="kv">
        <dt>مبلغ</dt><dd><?= money($o['subtotal']) ?></dd>
        <?php if ((int)$o['discount'] > 0): ?><dt>تخفیف<?= $coupon ? ' (' . e($coupon['code']) . ')' : '' ?></dt><dd style="color:var(--ok)">− <?= money($o['discount']) ?></dd><?php endif ?>
        <dt>قابل پرداخت</dt><dd style="font-size:1.2rem"><?= money($o['total']) ?></dd>
      </dl>

      <?php if ($o['status'] === 'pending'): ?>
        <p class="mt">زمان باقی‌مانده برای پرداخت: <span class="countdown" id="countdown" data-until="<?= (int)$o['hold_until'] ?>" data-now="<?= time() ?>">—</span></p>

        <?php if (!$o['coupon_id']): ?>
        <form method="post" action="<?= e(url('/order/' . $o['id'] . '/coupon')) ?>" class="row" style="flex-wrap:nowrap">
          <?= csrf_field() ?>
          <input type="text" name="code" placeholder="کد تخفیف" class="input-ltr" maxlength="32">
          <button class="btn ghost" type="submit">اعمال</button>
        </form>
        <?php else: ?>
        <form method="post" action="<?= e(url('/order/' . $o['id'] . '/coupon')) ?>">
          <?= csrf_field() ?><input type="hidden" name="remove" value="1">
          <button class="linkbtn small" type="submit">حذف کد تخفیف</button>
        </form>
        <?php endif ?>

        <form method="post" action="<?= e(url('/order/' . $o['id'] . '/pay')) ?>" class="mt" id="payForm">
          <?= csrf_field() ?>
          <button class="btn block lg" type="submit"><?= (int)$o['total'] === 0 ? 'ثبت نهایی و صدور بلیط' : 'پرداخت آنلاین ' . money($o['total']) ?></button>
        </form>
        <form method="post" action="<?= e(url('/order/' . $o['id'] . '/cancel')) ?>" class="center mt">
          <?= csrf_field() ?>
          <button class="linkbtn small" type="submit">انصراف و تغییر صندلی‌ها</button>
        </form>
      <?php else: ?>
        <div class="alert warn mt">این سفارش <?= e($stLabel) ?> است. لطفاً دوباره صندلی انتخاب کنید.</div>
        <a class="btn block" href="<?= e(url('/session/' . $o['session_id'])) ?>">انتخاب صندلی</a>
      <?php endif ?>
    </div>
  </div>

<?php endif ?>
