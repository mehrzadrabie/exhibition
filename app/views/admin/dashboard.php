<h1>داشبورد</h1>
<div class="grid g4">
  <div class="stat"><small>فروش امروز</small><b><?= money($todayStats['s'], false) ?></b><small><?= e(fa($todayStats['seats'])) ?> بلیط · <?= e(fa($todayStats['c'])) ?> سفارش</small></div>
  <div class="stat"><small>کل فروش (تومان)</small><b><?= money($totals['s'], false) ?></b><small><?= e(fa($totals['seats'])) ?> بلیط · <?= e(fa($totals['c'])) ?> سفارش</small></div>
  <div class="stat"><small>کاربران ثبت‌نام‌شده</small><b><?= e(fa($users)) ?></b></div>
  <div class="stat"><small>میانگین سبد</small><b><?= money($totals['c'] ? $totals['s'] / $totals['c'] : 0, false) ?></b><small>تومان</small></div>
</div>

<h2 class="mt">وضعیت سانس‌ها</h2>
<div class="grid g2">
<?php foreach ($sessions as $s): $st = $stats[$s['id']]; $cap = max(1, $st['total']); ?>
  <div class="card">
    <div class="row between"><b><?= e($s['title']) ?></b><span class="small muted"><?= e(jdate('j F H:i', $s['starts_at'])) ?></span></div>
    <div class="grid g4 mt small">
      <div>فروخته‌شده<br><b><?= e(fa($st['sold'])) ?></b> / <?= e(fa($st['total'])) ?></div>
      <div>رزرو موقت<br><b><?= e(fa($st['held'])) ?></b></div>
      <div>مسدود/VIP<br><b><?= e(fa($st['blocked'])) ?></b></div>
      <div>درآمد<br><b><?= money($st['revenue'], false) ?></b></div>
    </div>
    <div class="small mt">پر شدن سالن: <?= e(fa(round($st['sold'] * 100 / $cap))) ?>٪</div>
    <div class="bar"><i style="width:<?= round($st['sold'] * 100 / $cap) ?>%"></i></div>
    <div class="small mt">حضور: <?= e(fa($st['inside'])) ?> از <?= e(fa($st['tickets'])) ?> بلیط</div>
    <div class="bar"><i style="width:<?= $st['tickets'] ? round($st['inside'] * 100 / $st['tickets']) : 0 ?>%;background:var(--red)"></i></div>
    <div class="row mt">
      <a class="btn sm ghost" href="<?= e(url('/admin/sessions/' . $s['id'] . '/seats')) ?>">نقشه صندلی‌ها</a>
      <a class="btn sm ghost" href="<?= e(url('/admin/tickets', ['session' => $s['id']])) ?>">لیست حضور</a>
    </div>
  </div>
<?php endforeach ?>
</div>

<div class="grid g2 mt">
  <div class="card">
    <h3>فروش به تفکیک جایگاه</h3>
    <table class="t">
      <tr><th>جایگاه</th><th>تعداد</th><th>مبلغ (تومان)</th></tr>
      <?php foreach ($byCat as $c): ?>
      <tr><td><i class="swatch" style="background:<?= e($c['color']) ?>"></i> <?= e($c['name'] ?: '—') ?></td><td><?= e(fa($c['cnt'])) ?></td><td><?= money($c['rev'], false) ?></td></tr>
      <?php endforeach ?>
    </table>
  </div>
  <div class="card">
    <h3>فروش ۱۴ روز اخیر</h3>
    <?php $max = 1; foreach ($daily as $d) $max = max($max, (int)$d['seats']); ?>
    <?php if (!$daily): ?><p class="muted">هنوز فروشی ثبت نشده.</p><?php endif ?>
    <?php foreach ($daily as $d): ?>
      <div class="row small" style="flex-wrap:nowrap"><span style="width:90px"><?= e(jdate('j F', $d['d'])) ?></span>
        <div class="bar" style="flex:1;margin:0"><i style="width:<?= round($d['seats'] * 100 / $max) ?>%"></i></div>
        <span style="width:110px;text-align:left"><?= e(fa($d['seats'])) ?> بلیط</span></div>
    <?php endforeach ?>
  </div>
</div>

<div class="card">
  <div class="row between"><h3 class="mb0">آخرین سفارش‌های پرداخت‌شده</h3><a href="<?= e(url('/admin/orders')) ?>">همه</a></div>
  <div class="tablewrap"><table class="t mt">
    <tr><th>#</th><th>خریدار</th><th>سانس</th><th>تعداد</th><th>مبلغ</th><th>روش</th><th>زمان</th></tr>
    <?php foreach ($recent as $o): ?>
    <tr>
      <td><a href="<?= e(url('/admin/orders/' . $o['id'])) ?>"><?= e(fa($o['id'])) ?></a></td>
      <td><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?> <span class="muted ltr small"><?= e($o['mobile']) ?></span></td>
      <td><?= e($o['title']) ?></td><td><?= e(fa($o['seats_count'])) ?></td><td><?= money($o['total']) ?></td>
      <td><?= e(method_label($o['method'])) ?></td><td class="small"><?= e(jdate('j F H:i', $o['paid_at'])) ?></td>
    </tr>
    <?php endforeach ?>
  </table></div>
</div>
