<h1>سفارش‌ها</h1>
<form class="filters" method="get" action="<?= e(url('/admin/orders')) ?>">
  <?php if (!config('pretty_urls', true)): ?><input type="hidden" name="r" value="/admin/orders"><?php endif ?>
  <div class="field"><label>جستجو</label><input type="search" name="q" value="<?= e(input('q')) ?>" placeholder="موبایل، نام، شرکت، شماره سفارش، کد پیگیری"></div>
  <div class="field"><label>وضعیت</label><select name="status">
    <?php foreach (['paid' => 'پرداخت‌شده', 'pending' => 'در انتظار پرداخت', 'all' => 'همه', 'expired' => 'منقضی', 'cancelled' => 'لغو‌شده', 'failed' => 'ناموفق', 'refunded' => 'مسترد'] as $k => $v): ?>
      <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach ?></select></div>
  <div class="field"><label>سانس</label><select name="session"><option value="">همه</option>
    <?php foreach ($sessions as $s): ?><option value="<?= (int)$s['id'] ?>" <?= input('session') == $s['id'] ? 'selected' : '' ?>><?= e($s['title']) ?></option><?php endforeach ?></select></div>
  <button class="btn dark" type="submit">فیلتر</button>
</form>
<div class="card tablewrap">
<table class="t">
  <tr><th>#</th><th>خریدار</th><th>شرکت</th><th>سانس</th><th>تعداد</th><th>مبلغ</th><th>روش</th><th>وضعیت</th><th>تاریخ</th></tr>
  <?php foreach ($orders as $o): list($l, $c) = order_status_label($o['status']); ?>
  <tr>
    <td><a href="<?= e(url('/admin/orders/' . $o['id'])) ?>"><?= e(fa($o['id'])) ?></a></td>
    <td><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?><div class="small muted ltr"><?= e($o['mobile']) ?></div></td>
    <td class="small"><?= e($o['company']) ?></td>
    <td class="small"><?= e($o['title']) ?></td><td><?= e(fa($o['seats_count'])) ?></td><td><?= money($o['total']) ?></td>
    <td class="small"><?= e(method_label($o['method'])) ?></td>
    <td><span class="badge <?= e($c) ?>"><?= e($l) ?></span></td>
    <td class="small"><?= e(jdate('Y/m/d H:i', $o['paid_at'] ?: $o['created_at'])) ?></td>
  </tr>
  <?php endforeach ?>
  <?php if (!$orders): ?><tr><td colspan="9" class="empty">موردی پیدا نشد.</td></tr><?php endif ?>
</table>
<?= render('admin/_pager', ['pg' => $pg]) ?>
</div>
