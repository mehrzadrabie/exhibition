<div class="row between"><h1 class="mb0">سانس‌ها</h1><a class="btn" href="<?= e(url('/admin/sessions/new')) ?>">+ سانس جدید</a></div>
<p class="muted small">هر سانس (مثلاً روز اول و روز دوم) ظرفیت و قیمت‌گذاری جداگانه دارد.</p>
<div class="card tablewrap">
<table class="t">
  <tr><th>عنوان</th><th>زمان</th><th>فروش</th><th>نمایش عمومی</th><th></th></tr>
  <?php foreach ($sessions as $s): ?>
  <tr>
    <td><b><?= e($s['title']) ?></b><div class="small muted"><?= e($s['subtitle']) ?></div></td>
    <td><?= e(jdate('l j F Y H:i', $s['starts_at'])) ?></td>
    <td><?= $s['sale_open'] ? '<span class="badge ok">باز</span>' : '<span class="badge muted">بسته</span>' ?></td>
    <td><?= $s['is_public'] ? '<span class="badge ok">بله</span>' : '<span class="badge warn">فقط دعوتی</span>' ?></td>
    <td class="row"><a class="btn sm ghost" href="<?= e(url('/admin/sessions/' . $s['id'])) ?>">ویرایش و قیمت</a>
      <a class="btn sm ghost" href="<?= e(url('/admin/sessions/' . $s['id'] . '/seats')) ?>">صندلی‌ها</a></td>
  </tr>
  <?php endforeach ?>
</table>
</div>
