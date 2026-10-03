<?php list($l, $c) = order_status_label($o['status']); ?>
<div class="row between"><h1 class="mb0">سفارش <?= e(fa($o['id'])) ?></h1><span class="badge <?= e($c) ?>"><?= e($l) ?></span></div>
<div class="grid g2 mt">
  <div class="card">
    <h3>اطلاعات</h3>
    <dl class="kv">
      <dt>خریدار</dt><dd><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?></dd>
      <dt>موبایل</dt><dd class="ltr"><?= e($o['mobile']) ?></dd>
      <?php if ($o['company']): ?><dt>شرکت</dt><dd><?= e($o['company']) ?> <?= $o['job_title'] ? '– ' . e($o['job_title']) : '' ?></dd><?php endif ?>
      <dt>سانس</dt><dd><?= e($o['title']) ?></dd>
      <dt>مبلغ</dt><dd><?= money($o['subtotal']) ?></dd>
      <?php if ($o['discount']): ?><dt>تخفیف</dt><dd><?= money($o['discount']) ?> (<?= e($o['coupon_code']) ?>)</dd><?php endif ?>
      <dt>پرداختی</dt><dd><?= money($o['total']) ?></dd>
      <dt>روش</dt><dd><?= e(method_label($o['method'])) ?><?= $o['admin_name'] ? ' – ' . e($o['admin_name']) : '' ?></dd>
      <?php if ($o['ref_id']): ?><dt>کد پیگیری</dt><dd class="ltr"><?= e($o['ref_id']) ?></dd><?php endif ?>
      <?php if ($o['card_pan']): ?><dt>کارت</dt><dd class="ltr"><?= e($o['card_pan']) ?></dd><?php endif ?>
      <dt>ثبت</dt><dd><?= e(jdate('Y/m/d H:i', $o['created_at'])) ?></dd>
      <?php if ($o['paid_at']): ?><dt>پرداخت</dt><dd><?= e(jdate('Y/m/d H:i', $o['paid_at'])) ?></dd><?php endif ?>
    </dl>
    <form method="post" action="<?= e(url('/admin/orders/' . $o['id'])) ?>" class="mt">
      <?= csrf_field() ?><input type="hidden" name="action" value="note">
      <label>یادداشت</label>
      <div class="row" style="flex-wrap:nowrap"><input type="text" name="note" value="<?= e($o['note']) ?>"><button class="btn ghost sm" type="submit">ذخیره</button></div>
    </form>
  </div>
  <div class="card">
    <h3>صندلی‌ها و بلیط‌ها</h3>
    <table class="t">
      <?php if ($tickets): foreach ($tickets as $t): ?>
      <tr>
        <td><?= e(seat_label($t['row_no'], $t['seat_no'])) ?><?= $t['guest_name'] ? '<div class="small muted">' . e($t['guest_name']) . ' ' . e($t['guest_mobile']) . '</div>' : '' ?></td>
        <td class="mono small"><a href="<?= e(url('/t/' . ticket_token($t['code']))) ?>" target="_blank"><?= e($t['code']) ?></a></td>
        <td><?= $t['status'] !== 'valid' ? '<span class="badge bad">باطل</span>' : ($t['checked_in_at'] ? '<span class="badge ok">حاضر ' . e(jdate('H:i', $t['checked_in_at'])) . '</span>' : '<span class="badge muted">هنوز نیامده</span>') ?></td>
      </tr>
      <?php endforeach; else: foreach ($items as $it): ?>
      <tr><td><?= e(seat_label($it['row_no'], $it['seat_no'])) ?></td><td class="small"><?= e($it['cat']) ?></td><td><?= money($it['price']) ?></td></tr>
      <?php endforeach; endif ?>
    </table>
    <div class="row mt">
      <?php if ($o['status'] === 'paid'): ?>
        <form method="post" action="<?= e(url('/admin/orders/' . $o['id'])) ?>"><?= csrf_field() ?><button class="btn sm ghost" name="action" value="resend_sms">ارسال مجدد پیامک</button></form>
        <?php if (admin_can('admin')): ?>
        <form method="post" action="<?= e(url('/admin/orders/' . $o['id'])) ?>" data-confirm="همه بلیط‌های این سفارش باطل و صندلی‌ها آزاد شوند؟ (استرداد وجه دستی است)"><?= csrf_field() ?><button class="btn sm" name="action" value="refund">لغو سفارش و ابطال بلیط‌ها</button></form>
        <?php endif ?>
      <?php elseif ($o['status'] === 'pending'): ?>
        <form method="post" action="<?= e(url('/admin/orders/' . $o['id'])) ?>"><?= csrf_field() ?><button class="btn sm ghost" name="action" value="cancel_pending">لغو و آزادسازی صندلی‌ها</button></form>
      <?php endif ?>
    </div>
  </div>
</div>
