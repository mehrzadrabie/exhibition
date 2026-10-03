<h1><?= $s ? 'ویرایش سانس' : 'سانس جدید' ?></h1>
<form method="post" action="<?= e(url('/admin/sessions/' . ($s ? $s['id'] : 'new'))) ?>" class="card">
  <?= csrf_field() ?>
  <div class="grid g2">
    <div class="field"><label>عنوان *</label><input type="text" name="title" value="<?= e($s ? $s['title'] : '') ?>" required></div>
    <div class="field"><label>زیرعنوان</label><input type="text" name="subtitle" value="<?= e($s ? $s['subtitle'] : '') ?>"></div>
    <div class="field"><label>شروع (شمسی) *</label><input type="text" name="starts_at" class="input-ltr" placeholder="1405/07/22 16:00" value="<?= e($s ? datetime_to_jalali($s['starts_at']) : '') ?>" required></div>
    <div class="field"><label>پایان (شمسی)</label><input type="text" name="ends_at" class="input-ltr" placeholder="1405/07/22 21:00" value="<?= e($s ? datetime_to_jalali($s['ends_at']) : '') ?>"></div>
  </div>
  <div class="field"><label>توضیحات / برنامه</label><textarea name="description"><?= e($s ? $s['description'] : '') ?></textarea></div>
  <div class="grid g4">
    <div class="field"><label>حداکثر بلیط در هر سفارش</label><input type="number" name="max_per_order" value="<?= e($s ? $s['max_per_order'] : 10) ?>" min="1" max="50"></div>
    <div class="field"><label>ترتیب نمایش</label><input type="number" name="sort" value="<?= e($s ? $s['sort'] : 0) ?>"></div>
    <div class="field"><label>&nbsp;</label><label class="check"><input type="checkbox" name="sale_open" value="1" <?= !$s || $s['sale_open'] ? 'checked' : '' ?>> فروش باز است</label></div>
    <div class="field"><label>&nbsp;</label><label class="check"><input type="checkbox" name="is_public" value="1" <?= !$s || $s['is_public'] ? 'checked' : '' ?>> نمایش در سایت</label></div>
  </div>
  <h3 class="mt">قیمت جایگاه‌ها (تومان)</h3>
  <p class="small muted">برای جایگاهی که نباید فروخته شود (مثلاً VIP دعوتی) قیمت را خالی بگذارید. قیمت ۰ یعنی رایگان.</p>
  <div class="grid g3">
    <?php foreach ($cats as $c): ?>
    <div class="field"><label><i class="swatch" style="background:<?= e($c['color']) ?>"></i> <?= e($c['name']) ?></label>
      <input type="text" inputmode="numeric" name="price[<?= (int)$c['id'] ?>]" class="input-ltr" value="<?= isset($prices[$c['id']]) ? (int)$prices[$c['id']] : '' ?>"></div>
    <?php endforeach ?>
  </div>
  <div class="row mt">
    <button class="btn" type="submit">ذخیره</button>
    <a class="btn ghost" href="<?= e(url('/admin/sessions')) ?>">بازگشت</a>
  </div>
</form>
<?php if ($s): ?>
<form method="post" action="<?= e(url('/admin/sessions/' . $s['id'])) ?>" data-confirm="سانس حذف شود؟">
  <?= csrf_field() ?><input type="hidden" name="delete" value="1">
  <button class="linkbtn small" type="submit">حذف این سانس (فقط بدون فروش)</button>
</form>
<?php endif ?>
