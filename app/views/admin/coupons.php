<h1>کدهای تخفیف</h1>
<form method="post" action="<?= e(url('/admin/coupons')) ?>" class="card">
  <?= csrf_field() ?>
  <h3>کد جدید</h3>
  <div class="grid g4">
    <div class="field"><label>کد (لاتین)</label><input type="text" name="code" class="input-ltr" required placeholder="EARLY20"></div>
    <div class="field"><label>نوع</label><select name="type"><option value="percent">درصدی</option><option value="fixed">مبلغ ثابت (تومان)</option></select></div>
    <div class="field"><label>مقدار</label><input type="text" name="value" class="input-ltr" inputmode="numeric" required></div>
    <div class="field"><label>سقف دفعات استفاده</label><input type="text" name="max_uses" class="input-ltr" inputmode="numeric" placeholder="نامحدود"></div>
    <div class="field"><label>حداقل تعداد بلیط</label><input type="text" name="min_seats" class="input-ltr" value="1"></div>
    <div class="field"><label>فقط برای سانس</label><select name="session_id"><option value="">همه سانس‌ها</option>
      <?php foreach ($sessions as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['title']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label>انقضا (شمسی)</label><input type="text" name="expires_at" class="input-ltr" placeholder="1405/07/15 23:59"></div>
  </div>
  <button class="btn" type="submit">ساخت کد</button>
  <p class="small muted mt mb0">ایده: کد خرید گروهی سازمانی (مثلاً حداقل ۵ بلیط با ۱۵٪ تخفیف) برای کمپین «دعوت به مشارکت سازمانی».</p>
</form>
<div class="card tablewrap">
<table class="t">
  <tr><th>کد</th><th>تخفیف</th><th>استفاده</th><th>شرایط</th><th>انقضا</th><th>وضعیت</th><th></th></tr>
  <?php foreach ($coupons as $c): ?>
  <tr>
    <td class="mono"><?= e($c['code']) ?></td>
    <td><?= $c['type'] === 'percent' ? e(fa($c['value'])) . '٪' : money($c['value']) ?></td>
    <td><?= e(fa($c['used_count'])) ?><?= $c['max_uses'] !== null ? ' / ' . e(fa($c['max_uses'])) : '' ?></td>
    <td class="small"><?= (int)$c['min_seats'] > 1 ? 'حداقل ' . e(fa($c['min_seats'])) . ' بلیط' : '' ?> <?= e($c['title']) ?></td>
    <td class="small"><?= $c['expires_at'] ? e(jdate('Y/m/d H:i', $c['expires_at'])) : '—' ?></td>
    <td><?= $c['is_active'] ? '<span class="badge ok">فعال</span>' : '<span class="badge muted">غیرفعال</span>' ?></td>
    <td class="row">
      <form method="post" action="<?= e(url('/admin/coupons')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn sm ghost" name="action" value="toggle"><?= $c['is_active'] ? 'غیرفعال' : 'فعال' ?></button></form>
      <?php if (!$c['used_count']): ?><form method="post" action="<?= e(url('/admin/coupons')) ?>" data-confirm="حذف شود؟"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn sm ghost" name="action" value="delete">حذف</button></form><?php endif ?>
    </td>
  </tr>
  <?php endforeach ?>
</table>
</div>
