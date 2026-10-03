<div class="row between">
  <h1 class="mb0">بلیط‌های من</h1>
  <a class="btn ghost sm" href="<?= e(url('/profile')) ?>">ویرایش مشخصات</a>
</div>
<p class="muted"><?= e(user_display_name($u)) ?> · <span class="ltr"><?= e(fa($u['mobile'])) ?></span></p>

<?php
$pendingOrders = array_filter($orders, function ($o) { return $o['status'] === 'pending' && (int)$o['hold_until'] >= time(); });
foreach ($pendingOrders as $o): ?>
  <div class="alert warn">سفارش <?= e(fa($o['id'])) ?> (<?= e($o['title']) ?>) در انتظار پرداخت است. <a href="<?= e(url('/order/' . $o['id'])) ?>"><b>ادامه پرداخت</b></a></div>
<?php endforeach ?>

<?php if (!$tickets): ?>
  <div class="card empty">
    <p>هنوز بلیطی ندارید.</p>
    <a class="btn" href="<?= e(url('/')) ?>">خرید بلیط</a>
  </div>
<?php else: ?>
  <div class="grid g3">
    <?php foreach ($tickets as $t): ?>
    <div class="card">
      <div class="small muted"><?= e(jdate('l j F – H:i', $t['starts_at'])) ?></div>
      <b><?= e($t['title']) ?></b>
      <div><?= e(seat_label($t['row_no'], $t['seat_no'])) ?> <span class="muted small">(<?= e(section_name($t['section'])) ?>)</span></div>
      <?php if ($t['guest_name']): ?><div class="small">مهمان: <?= e($t['guest_name']) ?></div><?php endif ?>
      <div class="row between mt">
        <?php if ($t['checked_in_at']): ?><span class="badge muted">وارد شده</span><?php else: ?><span class="badge ok">معتبر</span><?php endif ?>
        <a class="btn sm" href="<?= e(url('/t/' . ticket_token($t['code']))) ?>">نمایش QR</a>
      </div>
    </div>
    <?php endforeach ?>
  </div>
  <h3 class="mt">سفارش‌ها</h3>
  <div class="card tablewrap">
    <table class="t">
      <tr><th>شماره</th><th>سانس</th><th>تعداد</th><th>مبلغ</th><th>وضعیت</th><th></th></tr>
      <?php foreach ($orders as $o): list($l, $c) = order_status_label($o['status']); ?>
      <tr>
        <td><?= e(fa($o['id'])) ?></td><td><?= e($o['title']) ?></td><td><?= e(fa($o['seats_count'])) ?></td>
        <td><?= money($o['total']) ?></td><td><span class="badge <?= e($c) ?>"><?= e($l) ?></span></td>
        <td><a href="<?= e(url('/order/' . $o['id'])) ?>">جزئیات</a></td>
      </tr>
      <?php endforeach ?>
    </table>
  </div>
<?php endif ?>
